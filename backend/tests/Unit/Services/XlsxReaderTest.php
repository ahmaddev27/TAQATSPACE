<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\ImportException;
use App\Services\Finance\Import\XlsxReader;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsXlsx;
use Tests\TestCase;

/**
 * The reader is hand-written (no spreadsheet library), so these files are laid
 * out the way Excel writes them: shared strings, phonetic runs, cells without a
 * reference, extension lists. Inline-strings-only fixtures hid every defect here.
 */
class XlsxReaderTest extends TestCase
{
    use BuildsXlsx;

    private const NS_XM = 'http://schemas.microsoft.com/office/excel/2006/main';

    /** @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>, numeric: list<string>}>} */
    private function read(UploadedFile $file): array
    {
        return (new XlsxReader)->read($file->getRealPath());
    }

    private function inline(string $reference, string $text): string
    {
        return '<c r="'.$reference.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
    }

    private function shared(string $reference, int $index): string
    {
        return '<c r="'.$reference.'" t="s"><v>'.$index.'</v></c>';
    }

    private function assertRejected(UploadedFile $file, string $messageKey, array $replace = []): void
    {
        try {
            $this->read($file);
            $this->fail('Expected the file to be refused.');
        } catch (ImportException $exception) {
            $this->assertSame(__('messages.'.$messageKey, $replace), $exception->getMessage());
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function test_a_phonetic_run_does_not_shift_the_next_shared_strings(): void
    {
        $items = '<si><t>code</t><rPh sb="0" eb="1"><t>kodo</t></rPh></si><si><t>name</t></si><si><t>value</t></si>';
        $sheet = '<row r="1">'.$this->shared('A1', 0).$this->shared('B1', 1).'</row><row r="2">'.$this->shared('A2', 2).$this->shared('B2', 1).'</row>';

        $parsed = $this->read($this->xlsxFromXml($sheet, $items));

        $this->assertSame(['code', 'name'], $parsed['headers']);
        $this->assertSame(['code' => 'value', 'name' => 'name'], $parsed['rows'][0]['values']);
    }

    public function test_an_inline_string_ignores_its_phonetic_run(): void
    {
        $sheet = '<row r="1"><c r="A1" t="inlineStr"><is><t>code</t><rPh sb="0" eb="1"><t>kodo</t></rPh></is></c></row><row r="2">'
            .'<c r="A2" t="inlineStr"><is><r><t>A</t></r><r><t>B</t></r><rPh sb="0" eb="1"><t>zzz</t></rPh></is></c></row>';

        $parsed = $this->read($this->xlsxFromXml($sheet));

        $this->assertSame(['code'], $parsed['headers']);
        $this->assertSame('AB', $parsed['rows'][0]['values']['code']);
    }

    public function test_an_empty_self_closing_shared_string_counts_as_an_empty_string(): void
    {
        $items = '<si><t>code</t></si><si/><si><t>kept</t></si>';
        $sheet = '<row r="1">'.$this->shared('A1', 0).$this->shared('B1', 0).'</row><row r="2">'.$this->shared('A2', 2).$this->shared('B2', 1).'</row>';

        $parsed = $this->read($this->xlsxFromXml($sheet, $items));

        $this->assertSame('kept', $parsed['rows'][0]['values']['code']);
    }

    public function test_cells_without_a_reference_follow_the_previous_column(): void
    {
        $sheet = '<row r="1"><c t="inlineStr"><is><t>code</t></is></c><c t="inlineStr"><is><t>name</t></is></c><c r="D1" t="inlineStr"><is><t>price</t></is></c><c t="inlineStr"><is><t>unit</t></is></c></row>'
            .'<row r="2"><c t="inlineStr"><is><t>a</t></is></c><c t="inlineStr"><is><t>b</t></is></c><c r="D2" t="inlineStr"><is><t>d</t></is></c><c t="inlineStr"><is><t>e</t></is></c></row>';

        $parsed = $this->read($this->xlsxFromXml($sheet));

        $this->assertSame(['code', 'name', '', 'price', 'unit'], $parsed['headers']);
        $this->assertSame(['code' => 'a', 'name' => 'b', 'price' => 'd', 'unit' => 'e'], array_diff_key($parsed['rows'][0]['values'], ['' => 1]));
    }

    public function test_an_empty_cell_at_a_far_column_is_refused_without_allocating(): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'<c r="ZZZZZZZ1"><v></v></c></row>';

        $this->assertRejected($this->xlsxFromXml($sheet), 'import_too_many_columns', ['max' => 30]);
    }

    public function test_an_empty_cell_at_the_column_limit_is_refused(): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'<c r="AE1"><v></v></c></row>';

        $this->assertRejected($this->xlsxFromXml($sheet), 'import_too_many_columns', ['max' => 30]);
    }

    public function test_styled_blank_cells_past_the_limit_are_ignored(): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'<c r="AE1" s="3"/></row><row r="2">'.$this->inline('A2', 'x').'</row>';

        $this->assertSame(['code'], $this->read($this->xlsxFromXml($sheet))['headers']);
    }

    public function test_a_shared_string_table_with_too_many_entries_is_refused_cleanly(): void
    {
        $items = str_repeat('<si/>', XlsxReader::MAX_SHARED_STRINGS + 1);
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'</row>';

        $this->assertRejected($this->xlsxFromXml($sheet, $items), 'import_file_too_complex');
    }

    public function test_an_xml_part_that_inflates_past_the_cap_is_refused_cleanly(): void
    {
        $padding = str_repeat(' ', XlsxReader::MAX_PART_BYTES + 1024);
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'</row>';

        $this->assertRejected($this->xlsxFromXml($sheet, null, $padding), 'import_file_too_complex');
    }

    public function test_extension_list_formulas_are_not_cell_formulas(): void
    {
        $extension = '<extLst><ext uri="{05C60535-1F16-4fd2-B633-F4F36F0B64E0}" xmlns:x14="http://schemas.microsoft.com/office/spreadsheetml/2009/9/main">'
            .'<x14:sparklineGroups xmlns:xm="'.self::NS_XM.'"><x14:sparklineGroup><x14:sparklines><x14:sparkline><xm:f>Sheet1!A2:B2</xm:f><xm:sqref>C2</xm:sqref></x14:sparkline></x14:sparklines></x14:sparklineGroup></x14:sparklineGroups></ext></extLst>';
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'</row><row r="2">'.$this->inline('A2', 'x').'</row>';

        $this->assertCount(1, $this->read($this->xlsxFromXml($sheet, null, $extension))['rows']);
    }

    /** @return array<string, array{string}> */
    public static function formulaCells(): array
    {
        return [
            'plain' => ['<c r="A2"><f>1+1</f><v>2</v></c>'],
            'shared' => ['<c r="A2"><f t="shared" ref="A2:A3" si="0">B2</f><v>2</v></c>'],
            'shared child' => ['<c r="A2"><f t="shared" si="0"/><v>2</v></c>'],
            'empty' => ['<c r="A2"><f></f><v>2</v></c>'],
        ];
    }

    #[DataProvider('formulaCells')]
    public function test_real_formulas_are_still_refused(string $cell): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'</row><row r="2">'.$cell.'</row>';

        $this->assertRejected($this->xlsxFromXml($sheet), 'import_formula_rejected');
    }

    public function test_numeric_floating_point_noise_is_shown_as_excel_shows_it(): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'a').$this->inline('B1', 'b').$this->inline('C1', 'c').$this->inline('D1', 'd').$this->inline('E1', 'e').'</row>'
            .'<row r="2"><c r="A2"><v>19.989999999999998</v></c><c r="B2"><v>0.30000000000000004</v></c><c r="C2"><v>1.005</v></c>'
            .'<c r="D2"><v>1.0000000000000001E-3</v></c>'.$this->inline('E2', '19.989999999999998').'</row>';

        $values = $this->read($this->xlsxFromXml($sheet))['rows'][0]['values'];

        $this->assertSame('19.99', $values['a']);
        $this->assertSame('0.3', $values['b']);
        $this->assertSame('1.005', $values['c']);
        $this->assertSame('0.001', $values['d']);
        $this->assertSame('19.989999999999998', $values['e']);
    }

    public function test_numeric_cells_are_reported_so_dates_can_tell_them_from_text(): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'a').$this->inline('B1', 'b').'</row><row r="2"><c r="A2"><v>46052</v></c>'.$this->inline('B2', '2026').'</row>';

        $this->assertSame(['a'], $this->read($this->xlsxFromXml($sheet))['rows'][0]['numeric']);
    }

    public function test_non_breaking_and_invisible_spaces_are_trimmed_from_headers_and_values(): void
    {
        $nbsp = "\u{00A0}";
        $sheet = '<row r="1">'.$this->inline('A1', $nbsp.'Code'.$nbsp).'</row><row r="2">'.$this->inline('A2', $nbsp."\u{200F}قهوة".$nbsp."\u{200B}").'</row>';

        $parsed = $this->read($this->xlsxFromXml($sheet));

        $this->assertSame(['code'], $parsed['headers']);
        $this->assertSame('قهوة', $parsed['rows'][0]['values']['code']);
    }

    public function test_malformed_xml_in_the_middle_of_a_sheet_is_a_damaged_file(): void
    {
        $sheet = '<row r="1">'.$this->inline('A1', 'code').'</row><row r="2">'.$this->inline('A2', 'x').'</row><row r="3"><c r="A3"></row></c><row r="4">'.$this->inline('A4', 'y').'</row>';

        $this->assertRejected($this->xlsxFromXml($sheet), 'import_damaged_file');
    }

    public function test_the_shared_strings_part_is_found_through_the_relationships(): void
    {
        $items = '<si><t>code</t></si><si><t>v</t></si>';
        $sheet = '<row r="1">'.$this->shared('A1', 0).'</row><row r="2">'.$this->shared('A2', 1).'</row>';

        $parsed = $this->read($this->xlsxFromXml($sheet, $items, '', 'xl/strings/table.xml'));

        $this->assertSame('v', $parsed['rows'][0]['values']['code']);
    }
}
