<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Builds small XLSX uploads for the import tests. A cell whose text starts with
 * "=" is written as a formula, so the reader's rejection can be exercised.
 */
trait BuildsXlsx
{
    /**
     * @param  list<list<string>>  $rows
     */
    protected function xlsx(array $rows, int $sheets = 1, string $name = 'import.xlsx', ?string $doctype = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tst');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $sheetTags = '';
        $rels = '';
        for ($index = 1; $index <= $sheets; $index++) {
            $sheetTags .= '<sheet name="S'.$index.'" sheetId="'.$index.'" r:id="rId'.$index.'"/>';
            $rels .= '<Relationship Id="rId'.$index.'" Type="x" Target="worksheets/sheet'.$index.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$index.'.xml', $this->sheetXml($rows, $doctype));
        }

        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetTags.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $this->freezeEntryTimes($zip);
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Writes a workbook from raw part XML, as Excel lays it out: the sheet may
     * use shared strings (the part is wired in through workbook.xml.rels at
     * $sharedPath), and the caller controls every element of <sheetData>.
     */
    protected function xlsxFromXml(string $sheetData, ?string $sharedItems = null, string $afterSheetData = '', string $sharedPath = 'xl/sharedStrings.xml', string $sheetNamespaces = ''): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tst');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $rels = '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
        if ($sharedItems !== null) {
            $rels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="'.preg_replace('#^xl/#', '', $sharedPath).'"/>';
            $zip->addFromString($sharedPath, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$sharedItems.'</sst>');
        }

        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="S1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'.$sheetNamespaces.'><sheetData>'.$sheetData.'</sheetData>'.$afterSheetData.'</worksheet>');
        $this->freezeEntryTimes($zip);
        $zip->close();

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Lays rows out like Excel does: every text goes into the shared-string
     * table and cells point at it with t="s"; numbers stay numeric cells.
     * A value written as ['n' => '5'] is a numeric cell.
     *
     * @param  list<list<string|array{n: string}>>  $rows
     */
    protected function xlsxShared(array $rows): UploadedFile
    {
        $items = [];
        $sheetData = '';
        foreach ($rows as $rowIndex => $row) {
            $sheetData .= '<row r="'.($rowIndex + 1).'">';
            foreach ($row as $columnIndex => $value) {
                $reference = chr(65 + $columnIndex).($rowIndex + 1);
                if (is_array($value)) {
                    $sheetData .= '<c r="'.$reference.'"><v>'.$value['n'].'</v></c>';

                    continue;
                }
                $items[] = '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>';
                $sheetData .= '<c r="'.$reference.'" t="s"><v>'.(count($items) - 1).'</v></c>';
            }
            $sheetData .= '</row>';
        }

        return $this->xlsxFromXml($sheetData, implode('', $items));
    }

    /** Entry times would make two builds of the same rows differ by hash whenever the clock ticks between them. */
    private function freezeEntryTimes(ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $zip->setMtimeIndex($index, 946684800);
        }
    }

    /** @param  list<list<string>>  $rows */
    private function sheetXml(array $rows, ?string $doctype): string
    {
        $xml = '';
        foreach ($rows as $rowIndex => $row) {
            $xml .= '<row r="'.($rowIndex + 1).'">';
            foreach ($row as $columnIndex => $value) {
                $reference = chr(65 + $columnIndex).($rowIndex + 1);
                $escaped = htmlspecialchars($value, ENT_XML1);
                $xml .= str_starts_with($value, '=')
                    ? '<c r="'.$reference.'"><f>'.htmlspecialchars(substr($value, 1), ENT_XML1).'</f><v>0</v></c>'
                    : '<c r="'.$reference.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c>';
            }
            $xml .= '</row>';
        }

        return ($doctype ?? '').'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>';
    }
}
