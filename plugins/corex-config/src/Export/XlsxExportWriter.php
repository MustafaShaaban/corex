<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use Closure;
use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

/**
 * Writes an export as an Excel workbook (spec 103, FR-016).
 *
 * A workbook is a zip of XML. This writes the parts a spreadsheet needs and no more: one sheet per
 * table, with a date as a date and a number as a number so they sort and filter, the heading row
 * bold and held in view, filtering on, and each column as wide as what it holds.
 *
 * It is written by hand because the alternative is a library many times the size of everything
 * else here, for a format this small a part of. What that costs is that nothing checks the file
 * but its tests, which read it back part by part.
 *
 * Each sheet's rows are read once and written to a working file as they come, so a large export is
 * not held in memory. The widths and the range to filter are only known at the end, and the parts
 * of the sheet that need them are written around the rows then.
 */
final readonly class XlsxExportWriter implements ExportWriter
{
    private const CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PACKAGE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';
    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    /** Cell styles, by their position in the workbook's list. */
    private const STYLE_HEADING = 1;
    private const STYLE_DATETIME = 2;

    /** The first number a workbook may give a format of its own. */
    private const DATETIME_FORMAT_ID = 164;
    private const DATETIME_FORMAT = 'yyyy-mm-dd hh:mm';

    /** A spreadsheet counts days from here. 25569 of them reach 1 January 1970. */
    private const DAYS_TO_UNIX_EPOCH = 25569;
    private const SECONDS_IN_A_DAY = 86400;

    private const NARROWEST_COLUMN = 8;
    private const WIDEST_COLUMN = 58;
    private const COLUMN_PADDING = 2;

    private const LONGEST_SHEET_NAME = 31;
    private const NOT_IN_A_SHEET_NAME = '/[\[\]:*?\/\\\\]+/';

    /** Everything XML 1.0 may hold. A character outside it makes the whole file unreadable. */
    private const NOT_IN_XML = '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

    /**
     * @param Closure():bool $rightToLeft Whether sheets are laid out right to left. Asked when a
     *                                    workbook is written, because it follows the site's language.
     */
    public function __construct(private Closure $rightToLeft)
    {
    }

    public function write(ExportDocument $document, string $pathWithoutExtension): ExportFile
    {
        $path    = $pathWithoutExtension . '.xlsx';
        $archive = new ZipArchive();
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('CoreX could not create the workbook.');
        }

        $names   = $this->sheetNames($document->sheets);
        $working = [];
        foreach ($document->sheets as $index => $sheet) {
            $working[] = $sheetPath = $path . '.sheet' . ($index + 1) . '.xml';
            $this->writeSheet($sheet, $sheetPath);
            $archive->addFile($sheetPath, sprintf('xl/worksheets/sheet%d.xml', $index + 1));
        }

        $archive->addFromString('[Content_Types].xml', $this->contentTypes(count($names)));
        $archive->addFromString('_rels/.rels', $this->packageRelationships());
        $archive->addFromString('xl/workbook.xml', $this->workbook($names));
        $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($names)));
        $archive->addFromString('xl/styles.xml', $this->styles());

        // The archive reads the sheets when it is closed, so they can only be removed after.
        $archive->close();
        array_map('unlink', $working);

        return new ExportFile($path, 'xlsx', self::CONTENT_TYPE);
    }

    private function writeSheet(ExportSheet $sheet, string $path): void
    {
        $rowsPath = $path . '.rows';
        $rows     = fopen($rowsPath, 'wb');
        if ($rows === false) {
            throw new RuntimeException('CoreX could not open the workbook working file.');
        }

        $widths = array_map(fn (string $heading): int => mb_strlen($heading), $sheet->headings);
        fwrite($rows, $this->row(1, array_map(
            fn (string $heading): string => $this->textCell($heading, self::STYLE_HEADING),
            $sheet->headings,
        )));

        $last = 1;
        foreach ($sheet->rows as $cells) {
            $last++;
            fwrite($rows, $this->row($last, array_map(fn (ExportCell $cell): string => $this->cell($cell), $cells)));
            foreach ($cells as $column => $cell) {
                $widths[$column] = max($widths[$column] ?? 0, mb_strlen($cell->asText()));
            }
        }
        fclose($rows);

        $this->assemble($path, $rowsPath, $widths, $last);
        unlink($rowsPath);
    }

    /**
     * The sheet's parts in the order the format requires: how it is viewed, its column widths, its
     * rows, then the range that is filtered.
     *
     * @param list<int> $widths The longest value in each column, in characters.
     */
    private function assemble(string $path, string $rowsPath, array $widths, int $lastRow): void
    {
        $sheet = fopen($path, 'wb');
        $rows  = fopen($rowsPath, 'rb');
        if ($sheet === false || $rows === false) {
            throw new RuntimeException('CoreX could not write the workbook sheet.');
        }

        fwrite($sheet, self::XML . sprintf('<worksheet xmlns="%s">', self::MAIN));
        fwrite($sheet, sprintf(
            '<sheetViews><sheetView workbookViewId="0"%s>'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>',
            ($this->rightToLeft)() ? ' rightToLeft="1"' : '',
        ));
        fwrite($sheet, $this->columns($widths));
        fwrite($sheet, '<sheetData>');
        stream_copy_to_stream($rows, $sheet);
        fwrite($sheet, '</sheetData>');
        fwrite($sheet, sprintf('<autoFilter ref="A1:%s%d"/>', $this->columnName(max(1, count($widths))), $lastRow));
        fwrite($sheet, '</worksheet>');

        fclose($rows);
        fclose($sheet);
    }

    /**
     * @param list<int> $widths
     */
    private function columns(array $widths): string
    {
        if ($widths === []) {
            return '';
        }

        $columns = '';
        foreach (array_values($widths) as $index => $characters) {
            $columns .= sprintf(
                '<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>',
                $index + 1,
                min(self::WIDEST_COLUMN, max(self::NARROWEST_COLUMN, $characters)) + self::COLUMN_PADDING,
            );
        }

        return '<cols>' . $columns . '</cols>';
    }

    /**
     * @param list<string> $cells Cells without their position.
     */
    private function row(int $number, array $cells): string
    {
        $row = '';
        foreach (array_values($cells) as $index => $cell) {
            $row .= sprintf($cell, $this->columnName($index + 1) . $number);
        }

        return sprintf('<row r="%d">%s</row>', $number, $row);
    }

    /**
     * A cell with `%s` where its position goes. The position is known to the row, not the cell.
     */
    private function cell(ExportCell $cell): string
    {
        return match ($cell->type) {
            ExportCell::NUMBER => '<c r="%s"><v>' . $this->number($cell->value) . '</v></c>',
            ExportCell::DATETIME => sprintf(
                '<c r="%%s" s="%d"><v>%s</v></c>',
                self::STYLE_DATETIME,
                $this->serial($cell->value),
            ),
            default => $this->textCell($cell->asText()),
        };
    }

    /**
     * Text held in the cell itself. A string cell is never evaluated, so text that begins with `=`
     * needs no guard here, and an apostrophe added to it would show.
     */
    private function textCell(string $text, int $style = 0): string
    {
        return sprintf(
            '<c r="%%s" t="inlineStr"%s><is><t xml:space="preserve">%s</t></is></c>',
            $style > 0 ? sprintf(' s="%d"', $style) : '',
            // `%` is doubled: the cell is a format string until its row places it.
            str_replace('%', '%%', $this->xml($text)),
        );
    }

    private function number(int|float|string|DateTimeImmutable $value): string
    {
        return is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.10F', (float) $value), '0'), '.');
    }

    /**
     * A moment as a spreadsheet stores it: days, and the time of day as a fraction of one. It has
     * no timezone, so the days are counted on the clock the moment was given in.
     */
    private function serial(int|float|string|DateTimeImmutable $moment): string
    {
        if (! $moment instanceof DateTimeImmutable) {
            return '0';
        }

        $onItsClock = $moment->getTimestamp() + $moment->getOffset();

        return sprintf('%.8F', $onItsClock / self::SECONDS_IN_A_DAY + self::DAYS_TO_UNIX_EPOCH);
    }

    private function xml(string $text): string
    {
        $clean = preg_replace(self::NOT_IN_XML, '', mb_scrub($text, 'UTF-8')) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * 1 is A, 26 is Z, 27 is AA.
     */
    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name      = chr(65 + $remainder) . $name;
            $number    = intdiv($number - 1, 26);
        }

        return $name;
    }

    /**
     * A sheet name may hold 31 characters, none of `[]:*?/\`, and may not be another sheet's name
     * in any capitals.
     *
     * @param list<ExportSheet> $sheets
     *
     * @return list<string>
     */
    private function sheetNames(array $sheets): array
    {
        $names = [];
        $used  = [];

        foreach ($sheets as $index => $sheet) {
            $base = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace(self::NOT_IN_A_SHEET_NAME, '', $sheet->name)), " '");
            $base = $base !== '' ? $base : sprintf('Sheet %d', $index + 1);
            $name = mb_substr($base, 0, self::LONGEST_SHEET_NAME);

            for ($copy = 2; isset($used[mb_strtolower($name)]); $copy++) {
                $suffix = sprintf(' (%d)', $copy);
                $name   = mb_substr($base, 0, self::LONGEST_SHEET_NAME - mb_strlen($suffix)) . $suffix;
            }

            $used[mb_strtolower($name)] = true;
            $names[]                    = $name;
        }

        return $names;
    }

    private function contentTypes(int $sheets): string
    {
        $overrides = '';
        for ($number = 1; $number <= $sheets; $number++) {
            $overrides .= sprintf(
                '<Override PartName="/xl/worksheets/sheet%d.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                $number,
            );
        }

        return self::XML
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function packageRelationships(): string
    {
        return self::XML
            . sprintf('<Relationships xmlns="%s">', self::PACKAGE_RELATIONSHIPS)
            . sprintf('<Relationship Id="rId1" Type="%s/officeDocument" Target="xl/workbook.xml"/>', self::RELATIONSHIPS)
            . '</Relationships>';
    }

    /**
     * @param list<string> $names
     */
    private function workbook(array $names): string
    {
        $sheets = '';
        foreach ($names as $index => $name) {
            $sheets .= sprintf('<sheet name="%s" sheetId="%2$d" r:id="rId%2$d"/>', $this->xml($name), $index + 1);
        }

        return self::XML
            . sprintf('<workbook xmlns="%s" xmlns:r="%s">', self::MAIN, self::RELATIONSHIPS)
            . '<bookViews><workbookView/></bookViews>'
            . '<sheets>' . $sheets . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelationships(int $sheets): string
    {
        $relationships = '';
        for ($number = 1; $number <= $sheets; $number++) {
            $relationships .= sprintf(
                '<Relationship Id="rId%1$d" Type="%2$s/worksheet" Target="worksheets/sheet%1$d.xml"/>',
                $number,
                self::RELATIONSHIPS,
            );
        }

        return self::XML
            . sprintf('<Relationships xmlns="%s">', self::PACKAGE_RELATIONSHIPS)
            . $relationships
            . sprintf('<Relationship Id="rId%d" Type="%s/styles" Target="styles.xml"/>', $sheets + 1, self::RELATIONSHIPS)
            . '</Relationships>';
    }

    /**
     * Three ways a cell is drawn: as it comes, as a heading, and as a date with a time. A
     * spreadsheet requires the two fills and the empty border that come first in their lists.
     */
    private function styles(): string
    {
        return self::XML
            . sprintf('<styleSheet xmlns="%s">', self::MAIN)
            . sprintf('<numFmts count="1"><numFmt numFmtId="%d" formatCode="%s"/></numFmts>', self::DATETIME_FORMAT_ID, self::DATETIME_FORMAT)
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEDEDED"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . sprintf('<xf numFmtId="%d" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>', self::DATETIME_FORMAT_ID)
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
