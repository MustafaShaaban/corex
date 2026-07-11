<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use RuntimeException;
use ZipArchive;

/** Incrementally builds formula-safe CSV or inline-string XLSX artifacts. */
final class DataExportArtifactWriter
{
    /** @param list<array{key:string,label:string}> $fields @param list<array<string,mixed>> $rows */
    public function append(DataExportArtifact $artifact, array $fields, array $rows, bool $final): DataExportArtifact
    {
        if ($artifact->format === 'csv') {
            return DataExportArtifact::start('csv', $this->csv($artifact->content, $fields, $rows));
        }
        $xml = $this->sheetXml($artifact->content, $fields, $rows);

        return DataExportArtifact::start('xlsx', $final ? $this->xlsx($xml . '</sheetData></worksheet>') : $xml);
    }

    /** @param list<array{key:string,label:string}> $fields @param list<array<string,mixed>> $rows */
    private function csv(string $current, array $fields, array $rows): string
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('The CSV export could not be created.');
        }
        if ($current !== '') {
            fwrite($handle, $current);
        } else {
            fputcsv($handle, array_column($fields, 'label'));
        }
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (array $field): string => $this->safe((string) ($row[$field['key']] ?? '')), $fields));
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return str_replace("\n", "\r\n", str_replace("\r\n", "\n", is_string($content) ? $content : ''));
    }

    /** @param list<array{key:string,label:string}> $fields @param list<array<string,mixed>> $rows */
    private function sheetXml(string $current, array $fields, array $rows): string
    {
        $xml = $current !== '' ? $current : '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $this->xmlRow(array_column($fields, 'label'));
        foreach ($rows as $row) {
            $xml .= $this->xmlRow(array_map(static fn (array $field): string => (string) ($row[$field['key']] ?? ''), $fields));
        }

        return $xml;
    }

    /** @param list<string> $values */
    private function xmlRow(array $values): string
    {
        return '<row>' . implode('', array_map(static fn (string $value): string =>
            '<c t="inlineStr"><is><t xml:space="preserve">'
            . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>', $values)) . '</row>';
    }

    private function xlsx(string $sheet): string
    {
        $path = tempnam(sys_get_temp_dir(), 'corex-xlsx-');
        if ($path === false) {
            throw new RuntimeException('The XLSX export could not allocate a temporary file.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            unlink($path);
            throw new RuntimeException('The XLSX export could not be packaged.');
        }
        foreach ($this->xlsxParts($sheet) as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        return is_string($content) ? $content : '';
    }

    /** @return array<string,string> */
    private function xlsxParts(string $sheet): array
    {
        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ];
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }
}
