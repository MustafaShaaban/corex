<?php

/**
 * Unit tests for the Excel workbook an export writes (spec 103, US6: FR-008, FR-016).
 *
 * The workbook is read back part by part, as a spreadsheet reads it: a zip of XML. Each case is
 * something the owner asked for by name — dates that sort as dates, numbers as numbers, the header
 * in view, filters on, a sheet per form — or something that makes a spreadsheet refuse the file.
 *
 * @package Corex\Tests\Unit\Config\Export
 */

declare(strict_types=1);

use Corex\Config\Export\ExportCell;
use Corex\Config\Export\ExportDocument;
use Corex\Config\Export\ExportSheet;
use Corex\Config\Export\XlsxExportWriter;

const XLSX_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

function workbookPath(): string
{
    $directory = sys_get_temp_dir() . '/corex_xlsx_' . uniqid('', true);
    mkdir($directory);

    return $directory . '/export';
}

/**
 * @param list<ExportSheet> $sheets
 *
 * @return array{file:\Corex\Config\Export\ExportFile,archive:ZipArchive}
 */
function writeWorkbook(array $sheets, bool $rightToLeft = false): array
{
    $file = (new XlsxExportWriter(static fn (): bool => $rightToLeft))->write(new ExportDocument('Submissions', $sheets), workbookPath());

    $archive = new ZipArchive();
    $archive->open($file->path);

    return ['file' => $file, 'archive' => $archive];
}

function workbookPart(ZipArchive $archive, string $name): SimpleXMLElement
{
    $xml = simplexml_load_string((string) $archive->getFromName($name));
    if ($xml === false) {
        throw new RuntimeException("$name is not XML a spreadsheet can read.");
    }
    $xml->registerXPathNamespace('m', XLSX_MAIN);

    return $xml;
}

/**
 * @return list<list<ExportCell>>
 */
function threeLeads(): array
{
    $at = static fn (string $time): ExportCell => ExportCell::dateTime(new DateTimeImmutable($time, new DateTimeZone('Africa/Cairo')));

    return [
        [ExportCell::number(41), $at('2026-10-07 12:30:00'), ExportCell::text('سلمى')],
        [ExportCell::number(42), $at('2026-10-08 09:00:00'), ExportCell::text('Omar & Sons <Ltd>')],
        [ExportCell::number(43), $at('2026-10-09 18:45:00'), ExportCell::text('=HYPERLINK("http://x")')],
    ];
}

function leadsSheet(string $name = 'Lead form'): ExportSheet
{
    return new ExportSheet($name, ['ID', 'Submitted', 'Name'], threeLeads());
}

it('writes a workbook a spreadsheet recognises', function () {
    ['file' => $file, 'archive' => $archive] = writeWorkbook([leadsSheet()]);

    $types = (string) $archive->getFromName('[Content_Types].xml');

    expect($file->extension)->toBe('xlsx')
        ->and($file->contentType)->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($types)->toContain('/xl/workbook.xml')
        ->and($types)->toContain('/xl/worksheets/sheet1.xml')
        ->and($types)->toContain('/xl/styles.xml')
        ->and($archive->getFromName('_rels/.rels'))->toContain('xl/workbook.xml')
        ->and($archive->getFromName('xl/_rels/workbook.xml.rels'))->toContain('worksheets/sheet1.xml');
});

it('carries a date as a date and a number as a number, so they sort and filter', function () {
    ['archive' => $archive] = writeWorkbook([leadsSheet()]);
    $sheet = workbookPart($archive, 'xl/worksheets/sheet1.xml');
    $styles = workbookPart($archive, 'xl/styles.xml');

    $id = $sheet->xpath('//m:c[@r="A2"]')[0];
    $submitted = $sheet->xpath('//m:c[@r="B2"]')[0];
    $dateStyle = $styles->xpath('//m:cellXfs/m:xf')[(int) $submitted['s']];
    $dateFormat = $styles->xpath(sprintf('//m:numFmt[@numFmtId="%d"]', (int) $dateStyle['numFmtId']))[0];

    expect((string) $id['t'])->toBe('')
        ->and((string) $id->v)->toBe('41')
        // 7 October 2026, 12:30, as a spreadsheet counts days: in the time the cell was given, not UTC.
        ->and(round((float) $submitted->v, 5))->toBe(46302.52083)
        ->and((string) $dateFormat['formatCode'])->toBe('yyyy-mm-dd hh:mm');
});

it('keeps text as text, whatever it begins with', function () {
    ['archive' => $archive] = writeWorkbook([leadsSheet()]);
    $raw = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
    $sheet = workbookPart($archive, 'xl/worksheets/sheet1.xml');

    $formula = $sheet->xpath('//m:c[@r="C4"]')[0];

    // A string cell is never evaluated. No apostrophe is added: in a workbook it would show.
    expect((string) $formula['t'])->toBe('inlineStr')
        ->and((string) $formula->is->t)->toBe('=HYPERLINK("http://x")')
        ->and($raw)->not->toContain('<f>')
        ->and((string) $sheet->xpath('//m:c[@r="C2"]')[0]->is->t)->toBe('سلمى')
        ->and((string) $sheet->xpath('//m:c[@r="C3"]')[0]->is->t)->toBe('Omar & Sons <Ltd>');
});

it('keeps the headings in view, bold, with filtering on over every row', function () {
    ['archive' => $archive] = writeWorkbook([leadsSheet()]);
    $sheet = workbookPart($archive, 'xl/worksheets/sheet1.xml');
    $styles = workbookPart($archive, 'xl/styles.xml');

    $pane = $sheet->xpath('//m:sheetView/m:pane')[0];
    $heading = $sheet->xpath('//m:c[@r="A1"]')[0];
    $headingStyle = $styles->xpath('//m:cellXfs/m:xf')[(int) $heading['s']];
    $headingFont = $styles->xpath('//m:fonts/m:font')[(int) $headingStyle['fontId']];

    expect((string) $pane['state'])->toBe('frozen')
        ->and((string) $pane['ySplit'])->toBe('1')
        ->and((string) $pane['topLeftCell'])->toBe('A2')
        ->and((string) $heading->is->t)->toBe('ID')
        ->and(isset($headingFont->b))->toBeTrue()
        ->and((string) $sheet->xpath('//m:autoFilter')[0]['ref'])->toBe('A1:C4');
});

it('sizes each column to what it holds, within reason', function () {
    $long = str_repeat('A long answer. ', 20);
    ['archive' => $archive] = writeWorkbook([
        new ExportSheet('Leads', ['ID', 'Message'], [[ExportCell::number(1), ExportCell::text($long)]]),
    ]);
    $columns = workbookPart($archive, 'xl/worksheets/sheet1.xml')->xpath('//m:cols/m:col');

    expect($columns)->toHaveCount(2)
        ->and((float) $columns[0]['width'])->toBeLessThan(15.0)
        // Wide enough to read, and not the width of the whole answer.
        ->and((float) $columns[1]['width'])->toBe(60.0);
});

it('gives each form a sheet of its own, named for it', function () {
    ['archive' => $archive] = writeWorkbook([
        leadsSheet('Lead form'),
        leadsSheet('Careers: apply [2026]'),
        leadsSheet('lead form'),
        leadsSheet('A form whose name is far longer than a sheet name may be'),
        leadsSheet(''),
    ]);
    $names = array_map(
        static fn (SimpleXMLElement $sheet): string => (string) $sheet['name'],
        workbookPart($archive, 'xl/workbook.xml')->xpath('//m:sheets/m:sheet'),
    );

    expect($names)->toBe([
        'Lead form',
        // The characters a sheet name may not hold are left out.
        'Careers apply 2026',
        // Two sheets may not share a name, and a spreadsheet does not tell capitals apart.
        'lead form (2)',
        // 31 characters is the most a sheet name may be.
        'A form whose name is far longer',
        'Sheet 5',
    ])
        ->and($archive->getFromName('xl/worksheets/sheet5.xml'))->not->toBeFalse();
});

it('lays the sheet out right to left for a right-to-left site', function () {
    ['archive' => $archive] = writeWorkbook([leadsSheet()], rightToLeft: true);

    expect((string) workbookPart($archive, 'xl/worksheets/sheet1.xml')->xpath('//m:sheetView')[0]['rightToLeft'])->toBe('1');
});

it('leaves out what XML cannot hold, so the file still opens', function () {
    ['archive' => $archive] = writeWorkbook([
        new ExportSheet('Leads', ['Message'], [[ExportCell::text("Line one\nLine two\x00\x0B end")]]),
    ]);

    expect((string) workbookPart($archive, 'xl/worksheets/sheet1.xml')->xpath('//m:c[@r="A2"]')[0]->is->t)
        ->toBe("Line one\nLine two end");
});

it('reads a sheet’s rows once, as they are produced', function () {
    $rows = (static function (): Generator {
        foreach (threeLeads() as $row) {
            yield $row;
        }
    })();
    ['archive' => $archive] = writeWorkbook([new ExportSheet('Leads', ['ID', 'Submitted', 'Name'], $rows)]);

    expect(workbookPart($archive, 'xl/worksheets/sheet1.xml')->xpath('//m:sheetData/m:row'))->toHaveCount(4);
});

it('writes a sheet with headings and no rows without filtering on nothing', function () {
    ['archive' => $archive] = writeWorkbook([new ExportSheet('Leads', ['ID', 'Name'], [])]);
    $sheet = workbookPart($archive, 'xl/worksheets/sheet1.xml');

    expect($sheet->xpath('//m:sheetData/m:row'))->toHaveCount(1)
        ->and((string) $sheet->xpath('//m:autoFilter')[0]['ref'])->toBe('A1:B1');
});

it('names a column past the twenty-sixth', function () {
    $headings = array_map(static fn (int $n): string => 'Q' . $n, range(1, 28));
    ['archive' => $archive] = writeWorkbook([new ExportSheet('Wide', $headings, [])]);
    $sheet = workbookPart($archive, 'xl/worksheets/sheet1.xml');

    expect((string) $sheet->xpath('//m:c[@r="AB1"]')[0]->is->t)->toBe('Q28')
        ->and((string) $sheet->xpath('//m:autoFilter')[0]['ref'])->toBe('A1:AB1');
});
