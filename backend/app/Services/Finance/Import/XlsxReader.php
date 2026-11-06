<?php

declare(strict_types=1);

namespace App\Services\Finance\Import;

use App\Exceptions\ImportException;
use XMLReader;
use ZipArchive;

/**
 * Reads a single-sheet XLSX with ZipArchive and XMLReader only, so the server
 * needs no spreadsheet library (and no gd extension). The upload is hostile:
 * the compressed size, the archive, every XML part, the shared-string count and
 * the row and column counts are capped before the data is used, DOCTYPE
 * declarations are refused (no XML external entities), malformed XML is a
 * damaged file and any formula cell rejects the whole file. Every value comes
 * back as text; numeric cells are also listed so a date serial can be told from
 * the text "2026".
 */
class XlsxReader
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public const MAX_ROWS = 2000;

    public const MAX_COLUMNS = 30;

    /** 2,000 rows x 30 columns is 60,000 cells, so even all-distinct strings fit with room to spare. */
    public const MAX_SHARED_STRINGS = 100000;

    /** A full 60,000-cell sheet is about 5 MB of XML; this leaves a wide margin for styles and extensions. */
    public const MAX_PART_BYTES = 20 * 1024 * 1024;

    private const MAX_SMALL_PART_BYTES = 1024 * 1024;

    private const MAX_ENTRIES = 100;

    private const MAX_ROW_ELEMENTS = 20000;

    private const MAX_CELL_CHARS = 2000;

    private const SIGNIFICANT_DIGITS = 15;

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const REL_SHARED_STRINGS = '/sharedStrings';

    /** @var list<string> */
    private array $tempFiles = [];

    /**
     * @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>, numeric: list<string>}>}
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
            $relationships = $this->relationships($zip);
            $shared = $this->sharedStringsPart($zip, $relationships);

            return $this->readSheet($this->xmlPart($zip, $this->sheetPath($zip, $relationships), self::MAX_PART_BYTES), $shared);
        } finally {
            $zip->close();
            array_map('unlink', array_filter($this->tempFiles, 'is_file'));
            $this->tempFiles = [];
        }
    }

    /**
     * Copies one archive part to a temp file, refusing one that inflates past
     * the cap whatever its header claims, so no part is ever held whole in memory.
     */
    private function extract(ZipArchive $zip, string $name, int $cap): string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            throw ImportException::notXlsx();
        }

        $stream = $stat['size'] > $cap ? false : $zip->getStream($name);
        if ($stream === false) {
            throw $stat['size'] > $cap ? ImportException::tooComplex() : ImportException::notXlsx();
        }

        $target = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        $this->tempFiles[] = $target;
        $copied = $this->copyCapped($stream, $target, $cap);
        fclose($stream);

        return $copied ? $target : throw ImportException::tooComplex();
    }

    /** @param  resource  $stream */
    private function copyCapped($stream, string $target, int $cap): bool
    {
        $out = fopen($target, 'wb');
        $total = 0;
        while (! feof($stream)) {
            $chunk = (string) fread($stream, 65536);
            $total += strlen($chunk);
            if ($total > $cap) {
                fclose($out);

                return false;
            }
            fwrite($out, $chunk);
        }
        fclose($out);

        return true;
    }

    private function xmlPart(ZipArchive $zip, string $name, int $cap): XMLReader
    {
        $reader = new XMLReader;
        $previous = libxml_use_internal_errors(true);
        $opened = $reader->open($this->extract($zip, $name, $cap), null, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        return $opened ? $reader : throw ImportException::notXlsx();
    }

    /** Moves to the next node, refusing DTDs and malformed XML (a parse error must not look like the end of the file). */
    private function advance(XMLReader $reader): bool
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $more = @$reader->read();
        $damaged = $this->hasFatalError();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($damaged) {
            throw ImportException::damaged();
        }

        if ($reader->nodeType === XMLReader::DOC_TYPE) {
            throw ImportException::notXlsx();
        }

        return $more;
    }

    private function hasFatalError(): bool
    {
        foreach (libxml_get_errors() as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{id: string, type: string, target: string}> */
    private function relationships(ZipArchive $zip): array
    {
        $rels = $this->xmlPart($zip, 'xl/_rels/workbook.xml.rels', self::MAX_SMALL_PART_BYTES);
        $found = [];
        while ($this->advance($rels)) {
            if ($rels->nodeType === XMLReader::ELEMENT && $rels->localName === 'Relationship') {
                $found[] = ['id' => (string) $rels->getAttribute('Id'), 'type' => (string) $rels->getAttribute('Type'), 'target' => (string) $rels->getAttribute('Target')];
            }
        }

        return $found;
    }

    private function partName(string $target): string
    {
        $target = ltrim($target, '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
    }

    /** @param  list<array{id: string, type: string, target: string}>  $relationships */
    private function sheetPath(ZipArchive $zip, array $relationships): string
    {
        $workbook = $this->xmlPart($zip, 'xl/workbook.xml', self::MAX_SMALL_PART_BYTES);
        $relationIds = [];
        while ($this->advance($workbook)) {
            if ($workbook->nodeType === XMLReader::ELEMENT && $workbook->localName === 'sheet') {
                $relationIds[] = (string) $workbook->getAttributeNs('id', self::NS_REL);
            }
        }

        if (count($relationIds) !== 1) {
            throw $relationIds === [] ? ImportException::notXlsx() : ImportException::singleSheetOnly();
        }

        foreach ($relationships as $relationship) {
            if ($relationship['id'] === $relationIds[0]) {
                return $this->partName($relationship['target']);
            }
        }

        throw ImportException::notXlsx();
    }

    /**
     * The shared-strings part is whatever the workbook relationships say it is.
     *
     * @param  list<array{id: string, type: string, target: string}>  $relationships
     * @return list<string>
     */
    private function sharedStringsPart(ZipArchive $zip, array $relationships): array
    {
        foreach ($relationships as $relationship) {
            if (str_ends_with($relationship['type'], self::REL_SHARED_STRINGS)) {
                return $this->sharedStrings($this->xmlPart($zip, $this->partName($relationship['target']), self::MAX_PART_BYTES));
            }
        }

        return [];
    }

    /** @return list<string> */
    private function sharedStrings(XMLReader $reader): array
    {
        $strings = [];
        $current = null;
        $phonetic = null;

        while ($this->advance($reader)) {
            if ($this->insidePhoneticRun($reader, $phonetic)) {
                continue;
            }

            $element = $reader->nodeType === XMLReader::ELEMENT;
            $name = $reader->localName;

            if ($element && $name === 'si') {
                $reader->isEmptyElement ? $strings[] = '' : $current = '';
            } elseif ($element && $name === 't' && $current !== null) {
                $current .= $reader->readString();
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $name === 'si' && $current !== null) {
                $strings[] = $current;
                $current = null;
            }

            if (count($strings) > self::MAX_SHARED_STRINGS) {
                throw ImportException::tooComplex();
            }
        }

        return $strings;
    }

    /**
     * Phonetic hints (<rPh>) are not part of a value. Tracks the depth of the
     * open run and reports true for every node inside it, so neither its text
     * nor the string after it is lost.
     */
    private function insidePhoneticRun(XMLReader $reader, ?int &$phonetic): bool
    {
        if ($phonetic !== null && $reader->depth <= $phonetic) {
            $phonetic = null;
        }

        if ($phonetic === null && $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'rPh' && ! $reader->isEmptyElement) {
            $phonetic = $reader->depth;

            return true;
        }

        return $phonetic !== null;
    }

    /**
     * @param  list<string>  $shared
     * @return array{headers: list<string>, rows: list<array{row: int, values: array<string, string>, numeric: list<string>}>}
     */
    private function readSheet(XMLReader $reader, array $shared): array
    {
        $headers = null;
        $rows = [];
        $rowElements = 0;
        $rowNumber = 0;
        $column = -1;
        $cells = [];
        $cell = null;
        $cellDepth = 0;
        $phonetic = null;

        while ($this->advance($reader)) {
            if ($this->insidePhoneticRun($reader, $phonetic)) {
                continue;
            }

            $type = $reader->nodeType;
            $name = $reader->localName;

            if ($type === XMLReader::ELEMENT && $name === 'f' && $cell !== null && $reader->depth === $cellDepth + 1 && $reader->namespaceURI === self::NS_MAIN) {
                throw ImportException::formula();
            }

            if ($type === XMLReader::ELEMENT && $name === 'row') {
                $rowNumber = (int) ($reader->getAttribute('r') ?: $rowNumber + 1);
                $cells = [];
                $column = -1;
                if (++$rowElements > self::MAX_ROW_ELEMENTS) {
                    throw ImportException::tooManyRows(self::MAX_ROWS);
                }
            } elseif ($type === XMLReader::ELEMENT && $name === 'c') {
                $column = $this->columnIndex($reader->getAttribute('r'), $column);
                if (! $reader->isEmptyElement) {
                    $this->assertColumnAllowed($column);
                    $cell = ['column' => $column, 'type' => (string) $reader->getAttribute('t'), 'value' => ''];
                    $cellDepth = $reader->depth;
                }
            } elseif ($type === XMLReader::ELEMENT && $this->isValueNode($name, $cell)) {
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
     * The text of an inline string lives in <t>, every other value in <v>.
     *
     * @param  array{column: int, type: string, value: string}|null  $cell
     */
    private function isValueNode(string $name, ?array $cell): bool
    {
        if ($cell === null) {
            return false;
        }

        return $cell['type'] === 'inlineStr' ? $name === 't' : $name === 'v';
    }

    /**
     * @param  array<int, array{value: string, numeric: bool}>  $cells
     * @param  list<string>|null  $headers
     * @param  list<array{row: int, values: array<string, string>, numeric: list<string>}>  $rows
     */
    private function collectRow(array $cells, int $rowNumber, ?array &$headers, array &$rows): void
    {
        if (array_filter($cells, static fn (array $cell): bool => $cell['value'] !== '') === []) {
            return;
        }

        if ($headers === null) {
            $headers = array_map('strtolower', $this->headerTexts($cells));

            return;
        }

        if (count($rows) >= self::MAX_ROWS) {
            throw ImportException::tooManyRows(self::MAX_ROWS);
        }

        $values = [];
        $numeric = [];
        foreach ($headers as $index => $header) {
            if (isset($values[$header])) {
                continue;
            }
            $values[$header] = $cells[$index]['value'] ?? '';
            if (($cells[$index]['numeric'] ?? false) && $values[$header] !== '') {
                $numeric[] = $header;
            }
        }
        $rows[] = ['row' => $rowNumber, 'values' => $values, 'numeric' => $numeric];
    }

    /**
     * Never wider than MAX_COLUMNS, because a cell at or past it is refused when it is read.
     *
     * @param  array<int, array{value: string, numeric: bool}>  $cells
     * @return list<string>
     */
    private function headerTexts(array $cells): array
    {
        $texts = [];
        for ($index = 0; $index <= max(array_keys($cells)); $index++) {
            $texts[] = $cells[$index]['value'] ?? '';
        }

        return $texts;
    }

    /**
     * @param  array{column: int, type: string, value: string}  $cell
     * @param  list<string>  $shared
     * @return array{value: string, numeric: bool}
     */
    private function cellValue(array $cell, array $shared): array
    {
        $numeric = $cell['type'] === '' || $cell['type'] === 'n';
        $value = match (true) {
            $cell['type'] === 's' => $shared[(int) $cell['value']] ?? '',
            $cell['type'] === 'b' => $cell['value'] === '1' ? '1' : '0',
            $numeric => $this->displayNumber(trim($cell['value'])),
            default => $cell['value'],
        };

        if (mb_strlen($value) > self::MAX_CELL_CHARS) {
            throw ImportException::notXlsx();
        }

        return ['value' => $this->trimUnicode($value), 'numeric' => $numeric];
    }

    /** Trims ordinary, non-breaking and invisible format characters (bidi marks, zero-width space, BOM). */
    private function trimUnicode(string $value): string
    {
        return preg_replace('/^[\s\p{Z}\p{Cf}]+|[\s\p{Z}\p{Cf}]+$/u', '', $value) ?? $value;
    }

    /**
     * Excel shows 15 significant digits, so 19.989999999999998 is 19.99. A
     * number that already fits is returned untouched.
     */
    private function displayNumber(string $text): string
    {
        $digits = strlen(preg_replace('/\D/', '', $text) ?? '');
        if (! is_numeric($text) || ($digits <= self::SIGNIFICANT_DIGITS && stripos($text, 'e') === false)) {
            return $text;
        }

        $number = (float) $text;
        if ($number === 0.0 || ! is_finite($number)) {
            return $text;
        }

        $decimals = self::SIGNIFICANT_DIGITS - 1 - (int) floor(log10(abs($number)));
        $formatted = number_format(round($number, $decimals), max(0, min($decimals, 30)), '.', '');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    private function assertColumnAllowed(int $column): void
    {
        if ($column >= self::MAX_COLUMNS) {
            throw ImportException::tooManyColumns(self::MAX_COLUMNS);
        }
    }

    /** "C7" => 2 (zero based); a cell without a usable reference sits right after the previous one. */
    private function columnIndex(?string $reference, int $previous): int
    {
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $reference) ?? '');
        if ($letters === '') {
            return $previous + 1;
        }

        $index = 0;
        foreach (str_split(substr($letters, 0, 8)) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
