<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Writes an export as comma-separated text that a spreadsheet opens correctly when the file is
 * double-clicked (spec 103, FR-017).
 *
 * That takes three things a plain CSV does not have: a byte-order mark, without which Excel reads
 * UTF-8 as the machine's local encoding and Arabic or accented names arrive garbled; the line
 * ending Excel writes itself; and a separator the reader's region expects.
 *
 * A document with several sheets becomes one file per sheet in an archive, because each has its
 * own columns (FR-018).
 */
final readonly class CsvExportWriter implements ExportWriter
{
    public const SEPARATORS = [',', ';', "\t"];

    private const BYTE_ORDER_MARK = "\xEF\xBB\xBF";
    private const LINE_ENDING = "\r\n";

    /** Characters a file name may not hold on one system or another. */
    private const UNSAFE_IN_FILE_NAMES = '/[\\\\\/:*?"<>|\x00-\x1F]+/';

    public function __construct(private string $separator = ',')
    {
        if (! in_array($separator, self::SEPARATORS, true)) {
            throw new InvalidArgumentException('The CSV separator must be a comma, a semicolon or a tab.');
        }
    }

    public function write(ExportDocument $document, string $pathWithoutExtension): ExportFile
    {
        if (count($document->sheets) === 1) {
            $path = $pathWithoutExtension . '.csv';
            $this->writeSheet($document->sheets[0], $path);

            return new ExportFile($path, 'csv', 'text/csv; charset=utf-8');
        }

        return $this->writeArchive($document, $pathWithoutExtension . '.zip');
    }

    private function writeArchive(ExportDocument $document, string $path): ExportFile
    {
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('CoreX could not create the export archive.');
        }

        $written = [];
        foreach ($this->fileNames($document->sheets) as $index => $name) {
            $sheetPath = $path . '.' . $index . '.csv';
            $this->writeSheet($document->sheets[$index], $sheetPath);
            $archive->addFile($sheetPath, $name);
            $written[] = $sheetPath;
        }

        // The archive reads its files when it is closed, so they can only be removed after.
        $archive->close();
        array_map('unlink', $written);

        return new ExportFile($path, 'zip', 'application/zip');
    }

    /**
     * One file name per sheet, safe on any system and never the same twice.
     *
     * @param list<ExportSheet> $sheets
     *
     * @return list<string>
     */
    private function fileNames(array $sheets): array
    {
        $names = [];
        $used  = [];

        foreach ($sheets as $sheet) {
            $base  = trim((string) preg_replace(self::UNSAFE_IN_FILE_NAMES, '', $sheet->name)) ?: 'export';
            $count = $used[$base] = ($used[$base] ?? 0) + 1;

            $names[] = ($count === 1 ? $base : sprintf('%s (%d)', $base, $count)) . '.csv';
        }

        return $names;
    }

    private function writeSheet(ExportSheet $sheet, string $path): void
    {
        $stream = fopen($path, 'wb');
        if ($stream === false) {
            throw new RuntimeException('CoreX could not open the export file.');
        }

        fwrite($stream, self::BYTE_ORDER_MARK);
        $this->line($stream, $sheet->headings);

        foreach ($sheet->rows as $row) {
            $this->line($stream, array_map(fn (ExportCell $cell): string => $this->written($cell), $row));
        }

        fclose($stream);
    }

    /**
     * @param resource     $stream
     * @param list<string> $values
     */
    private function line($stream, array $values): void
    {
        fputcsv($stream, $values, $this->separator, '"', '', self::LINE_ENDING);
    }

    /**
     * Text that begins with `=`, `+`, `-` or `@` is run as a formula by a spreadsheet, and a
     * leading space or tab does not stop it. An apostrophe in front makes it text. A number is
     * left alone: a negative one begins with a minus and is not a formula.
     */
    private function written(ExportCell $cell): string
    {
        $text = $cell->asText();

        return $cell->type === ExportCell::TEXT && preg_match('/^\s*[=+\-@]/', $text) === 1
            ? "'" . $text
            : $text;
    }
}
