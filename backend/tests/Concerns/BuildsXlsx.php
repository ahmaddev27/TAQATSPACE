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
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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
