<?php

declare(strict_types=1);

namespace App\Services\Finance\Import;

use App\Exceptions\ImportException;
use XMLReader;
use ZipArchive;

/**
 * Reads a single-sheet XLSX with ZipArchive and XMLReader only, so the server
 * needs no spreadsheet library (and no gd extension). The upload is hostile:
 * the compressed size, the archive, every XML part and the row count are capped
 * before the data is used, DOCTYPE declarations are refused (no XML external
 * entities) and any formula cell rejects the whole file. Every value comes back
 * as the text stored in the file.
 */
class XlsxReader
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_ROWS = 2000;

    public const MAX_COLUMNS = 30;

    private const MAX_ENTRIES = 100;

    private const MAX_XML_BYTES = 32 * 1024 * 1024;

    private const MAX_ROW_ELEMENTS = 20000;

    private const MAX_CELL_CHARS = 2000;

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>}>}
     */
    public function read(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ImportException::unsupportedServer();
        }

        if (filesize($path) > self::MAX_BYTES) {
            throw ImportException::tooLarge(self::MAX_BYTES / 1024 / 1024);
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true || $zip->numFiles > self::MAX_ENTRIES) {
            throw ImportException::notXlsx();
        }

        try {
            $sheet = $this->entry($zip, $this->sheetPath($zip));
            $shared = $zip->locateName('xl/sharedStrings.xml') === false ? [] : $this->sharedStrings($this->entry($zip, 'xl/sharedStrings.xml'));

            return $this->readSheet($sheet, $shared);
        } finally {
            $zip->close();
        }
    }

    /** Reads one archive part, refusing one that inflates past the cap whatever its header claims. */
    private function entry(ZipArchive $zip, string $name): string
    {
        $stat = $zip->statName($name);
        if ($stat === false || $stat['size'] > self::MAX_XML_BYTES) {
            throw $stat === false ? ImportException::notXlsx() : ImportException::tooLarge(self::MAX_BYTES / 1024 / 1024);
        }

        $stream = $zip->getStream($name);
        $xml = $stream === false ? false : stream_get_contents($stream, self::MAX_XML_BYTES + 1);
        if ($xml === false || strlen($xml) > self::MAX_XML_BYTES) {
            throw ImportException::notXlsx();
        }

        return $xml;
    }

    private function xml(string $content): XMLReader
    {
        $reader = new XMLReader;
        $previous = libxml_use_internal_errors(true);
        $opened = $reader->XML($content, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_use_internal_errors($previous);

        return $opened ? $reader : throw ImportException::notXlsx();
    }

    /** Moves to the next node, refusing DTDs and malformed XML. */
    private function advance(XMLReader $reader): bool
    {
        $previous = libxml_use_internal_errors(true);
        $more = @$reader->read();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($reader->nodeType === XMLReader::DOC_TYPE) {
            throw ImportException::notXlsx();
        }

        return $more;
    }

    private function sheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xml($this->entry($zip, 'xl/workbook.xml'));
        $relationIds = [];
        while ($this->advance($workbook)) {
            if ($workbook->nodeType === XMLReader::ELEMENT && $workbook->localName === 'sheet') {
                $relationIds[] = (string) $workbook->getAttributeNs('id', self::NS_REL);
            }
        }

        if (count($relationIds) !== 1) {
            throw $relationIds === [] ? ImportException::notXlsx() : ImportException::singleSheetOnly();
        }

        $target = $this->relationshipTarget($this->entry($zip, 'xl/_rels/workbook.xml.rels'), $relationIds[0]);
        $target = ltrim($target, '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
    }

    private function relationshipTarget(string $relsXml, string $id): string
    {
        $rels = $this->xml($relsXml);
        while ($this->advance($rels)) {
            if ($rels->nodeType === XMLReader::ELEMENT && $rels->localName === 'Relationship' && $rels->getAttribute('Id') === $id) {
                return (string) $rels->getAttribute('Target');
            }
        }

        throw ImportException::notXlsx();
    }

    /** @return list<string> */
    private function sharedStrings(string $content): array
    {
        $reader = $this->xml($content);
        $strings = [];
        $current = null;

        while ($this->advance($reader)) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                $current = '';
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'rPh') {
                $reader->next(); // phonetic hints are not part of the value
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 't' && $current !== null) {
                $current .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'si') {
                $strings[] = (string) $current;
                $current = null;
            }
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     * @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>}>}
     */
    private function readSheet(string $content, array $shared): array
    {
        $reader = $this->xml($content);
        $headers = null;
        $rows = [];
        $rowElements = 0;
        $rowNumber = 0;
        $cells = [];
        $cell = null;

        while ($this->advance($reader)) {
            $type = $reader->nodeType;
            $name = $reader->localName;

            if ($type === XMLReader::ELEMENT && $name === 'f') {
                throw ImportException::formula();
            }

            if ($type === XMLReader::ELEMENT && $name === 'row') {
                $rowNumber = (int) ($reader->getAttribute('r') ?: $rowNumber + 1);
                $cells = [];
                if (++$rowElements > self::MAX_ROW_ELEMENTS) {
                    throw ImportException::tooManyRows(self::MAX_ROWS);
                }
            } elseif ($type === XMLReader::ELEMENT && $name === 'c' && ! $reader->isEmptyElement) {
                $cell = ['column' => $this->columnIndex((string) $reader->getAttribute('r')), 'type' => (string) $reader->getAttribute('t'), 'value' => ''];
            } elseif ($type === XMLReader::ELEMENT && ($name === 'v' || $name === 't') && $cell !== null) {
                $cell['value'] .= $reader->readString();
            } elseif ($type === XMLReader::END_ELEMENT && $name === 'c' && $cell !== null) {
                $cells[$cell['column']] = $this->cellValue($cell, $shared);
                $cell = null;
            } elseif ($type === XMLReader::END_ELEMENT && $name === 'row') {
                $this->collectRow($cells, $rowNumber, $headers, $rows);
            }
        }

        if ($headers === null) {
            throw ImportException::empty();
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  array<int, string>  $cells
     * @param  list<string>|null  $headers
     * @param  list<array{row: int, values: array<string, string>}>  $rows
     */
    private function collectRow(array $cells, int $rowNumber, ?array &$headers, array &$rows): void
    {
        if (array_filter($cells, static fn (string $value): bool => $value !== '') === []) {
            return;
        }

        if ($headers === null) {
            $headers = array_map(static fn (string $value): string => strtolower(trim($value)), $this->padded($cells, max(array_keys($cells)) + 1));

            return;
        }

        if (count($rows) >= self::MAX_ROWS) {
            throw ImportException::tooManyRows(self::MAX_ROWS);
        }

        $values = [];
        foreach ($headers as $index => $header) {
            $values[$header] ??= $cells[$index] ?? '';
        }
        $rows[] = ['row' => $rowNumber, 'values' => $values];
    }

    /**
     * @param  array<int, string>  $cells
     * @return list<string>
     */
    private function padded(array $cells, int $length): array
    {
        $padded = [];
        for ($index = 0; $index < $length; $index++) {
            $padded[] = $cells[$index] ?? '';
        }

        return $padded;
    }

    /**
     * @param  array{column: int, type: string, value: string}  $cell
     * @param  list<string>  $shared
     */
    private function cellValue(array $cell, array $shared): string
    {
        $value = match ($cell['type']) {
            's' => $shared[(int) $cell['value']] ?? '',
            'b' => $cell['value'] === '1' ? '1' : '0',
            default => $cell['value'],
        };

        if (mb_strlen($value) > self::MAX_CELL_CHARS) {
            throw ImportException::notXlsx();
        }

        if ($cell['column'] >= self::MAX_COLUMNS && trim($value) !== '') {
            throw ImportException::tooManyColumns(self::MAX_COLUMNS);
        }

        return trim($value);
    }

    /** "C7" => 2 (zero based). */
    private function columnIndex(string $reference): int
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $reference) ?? '');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }
}
