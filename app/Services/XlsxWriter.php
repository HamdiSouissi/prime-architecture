<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Minimal, dependency-free XLSX writer using ext-zip.
 *
 * A dedicated library (openspout or PhpSpreadsheet) was considered, but this
 * project cannot install Composer packages in the build environment and the
 * report exports only need inline strings, numbers and a currency format. The
 * class stays behind this small interface so a library can replace it later.
 */
class XlsxWriter
{
    private const CURRENCY_FORMAT = '#,##0.000';

    /**
     * @param  list<array{name: string, headers: list<string>, rows: list<list<string|int|float|null>>, currency_columns?: list<int>}>  $sheets
     */
    public function write(string $path, array $sheets): void
    {
        if ($sheets === []) {
            throw new InvalidArgumentException('At least one sheet is required.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The zip extension is required to export XLSX files.');
        }
        $names = [];
        foreach ($sheets as $sheet) {
            $name = $this->sheetName((string) $sheet['name']);
            if (in_array(mb_strtolower($name), $names, true)) {
                throw new InvalidArgumentException('Sheet names must be unique.');
            }
            $names[] = mb_strtolower($name);
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the XLSX archive.');
        }
        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
            $zip->addFromString('_rels/.rels', $this->packageRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
            $zip->addFromString('xl/styles.xml', $this->styles());
            foreach (array_values($sheets) as $index => $sheet) {
                $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet));
            }
        } finally {
            $zip->close();
        }
    }

    private function sheetName(string $name): string
    {
        $clean = trim(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $name) ?? '');
        $clean = mb_substr($clean === '' ? 'Rapport' : $clean, 0, 31);

        return $clean;
    }

    private function contentTypes(int $count): string
    {
        $overrides = '';
        for ($index = 1; $index <= $count; $index++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /** @param list<array{name: string, headers: list<string>, rows: list<list<string|int|float|null>>, currency_columns?: list<int>}> $sheets */
    private function workbook(array $sheets): string
    {
        $entries = '';
        foreach (array_values($sheets) as $index => $sheet) {
            $id = $index + 1;
            $entries .= '<sheet name="'.$this->escape($this->sheetName((string) $sheet['name'])).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$entries.'</sheets></workbook>';
    }

    private function workbookRelationships(int $count): string
    {
        $entries = '';
        for ($index = 1; $index <= $count; $index++) {
            $entries .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }
        $entries .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$entries.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="'.self::CURRENCY_FORMAT.'"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF2FF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'</styleSheet>';
    }

    /** @param array{name: string, headers: list<string>, rows: list<list<string|int|float|null>>, currency_columns?: list<int>} $sheet */
    private function worksheet(array $sheet): string
    {
        $currency = $sheet['currency_columns'] ?? [];
        $rows = [];
        $rowNumber = 1;
        $headerCells = '';
        foreach (array_values($sheet['headers']) as $column => $header) {
            $headerCells .= $this->cell($column, $rowNumber, $header, 1);
        }
        $rows[] = '<row r="'.$rowNumber.'">'.$headerCells.'</row>';
        foreach ($sheet['rows'] as $row) {
            $rowNumber++;
            $cells = '';
            foreach (array_values($row) as $column => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $cells .= $this->cell($column, $rowNumber, $value, in_array($column, $currency, true) ? 2 : 0);
            }
            $rows[] = '<row r="'.$rowNumber.'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .implode('', $rows).'</sheetData></worksheet>';
    }

    private function cell(int $column, int $row, string|int|float $value, int $style): string
    {
        $reference = $this->columnLetter($column).$row;
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"'.($style ? ' s="'.$style.'"' : '').'><v>'.$value.'</v></c>';
        }
        if ($style === 2 && preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D', $value)) {
            return '<c r="'.$reference.'" s="2"><v>'.$value.'</v></c>';
        }

        return '<c r="'.$reference.'" t="inlineStr"'.($style === 1 ? ' s="1"' : '').'><is><t xml:space="preserve">'.$this->escape($value).'</t></is></c>';
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
