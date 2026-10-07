<?php

/**
 * Unit tests for the CSV an export writes (spec 103, US7: FR-008, FR-017, FR-018).
 *
 * The file is read back as bytes: what matters is what a spreadsheet meets when the file is
 * double-clicked, and that is not visible through a CSV parser.
 *
 * @package Corex\Tests\Unit\Config\Export
 */

declare(strict_types=1);

use Corex\Config\Export\CsvExportWriter;
use Corex\Config\Export\ExportCell;
use Corex\Config\Export\ExportDocument;
use Corex\Config\Export\ExportSheet;

const CSV_BYTE_ORDER_MARK = "\xEF\xBB\xBF";

function exportScratchPath(): string
{
    $directory = sys_get_temp_dir() . '/corex_export_' . uniqid('', true);
    mkdir($directory);

    return $directory . '/export';
}

/**
 * @param list<list<ExportCell>> $rows
 */
function leadSheet(array $rows, string $name = 'Lead form'): ExportSheet
{
    return new ExportSheet($name, ['ID', 'Name', 'Submitted'], $rows);
}

/**
 * @return list<ExportCell>
 */
function leadRow(int $id, string $name): array
{
    return [
        ExportCell::number($id),
        ExportCell::text($name),
        ExportCell::dateTime(new DateTimeImmutable('2026-10-07 12:30:00', new DateTimeZone('Africa/Cairo'))),
    ];
}

it('writes a file a spreadsheet opens in the right encoding, one line per row', function () {
    $file = (new CsvExportWriter())->write(
        new ExportDocument('Leads', [leadSheet([leadRow(41, 'سلمى'), leadRow(42, 'Zoë')])]),
        exportScratchPath(),
    );

    expect($file->extension)->toBe('csv')
        ->and($file->contentType)->toBe('text/csv; charset=utf-8')
        ->and(file_get_contents($file->path))->toBe(
            CSV_BYTE_ORDER_MARK
            . "ID,Name,Submitted\r\n"
            . "41,سلمى,\"2026-10-07 12:30\"\r\n"
            . "42,Zoë,\"2026-10-07 12:30\"\r\n",
        );
});

it('separates values with the separator asked for', function () {
    $file = (new CsvExportWriter(';'))->write(
        new ExportDocument('Leads', [leadSheet([leadRow(41, 'Salma; Omar')])]),
        exportScratchPath(),
    );

    expect(file_get_contents($file->path))->toBe(
        CSV_BYTE_ORDER_MARK . "ID;Name;Submitted\r\n" . "41;\"Salma; Omar\";\"2026-10-07 12:30\"\r\n",
    );
});

it('refuses a separator a spreadsheet would not read', function () {
    new CsvExportWriter('|');
})->throws(InvalidArgumentException::class);

/**
 * A cell that begins with one of these is run as a formula by a spreadsheet. The answer came from
 * a visitor; the person opening the file is staff.
 */
it('writes text a spreadsheet would run as a formula so that it is shown instead', function (string $answer, string $written) {
    $file = (new CsvExportWriter())->write(
        new ExportDocument('Leads', [new ExportSheet('Leads', ['Answer'], [[ExportCell::text($answer)]])]),
        exportScratchPath(),
    );

    expect(file_get_contents($file->path))->toBe(CSV_BYTE_ORDER_MARK . "Answer\r\n" . $written . "\r\n");
})->with([
    'an equals sign' => ['=1+1', "'=1+1"],
    'a plus' => ['+20 101 699 9700', "\"'+20 101 699 9700\""],
    'a minus' => ['-cmd', "'-cmd"],
    'an at sign' => ['@SUM(A1)', "'@SUM(A1)"],
    'one hidden behind a space' => [' =1+1', "\"' =1+1\""],
    'one hidden behind a tab' => ["\t=1+1", "\"'\t=1+1\""],
    'ordinary text' => ['Branding', 'Branding'],
]);

it('leaves a negative number a number', function () {
    $file = (new CsvExportWriter())->write(
        new ExportDocument('Leads', [new ExportSheet('Leads', ['Balance'], [[ExportCell::number(-5)]])]),
        exportScratchPath(),
    );

    expect(file_get_contents($file->path))->toBe(CSV_BYTE_ORDER_MARK . "Balance\r\n-5\r\n");
});

it('puts each form in a file of its own, in one archive, when there are several', function () {
    $file = (new CsvExportWriter())->write(
        new ExportDocument('Submissions', [
            leadSheet([leadRow(41, 'Salma')], 'Lead form'),
            leadSheet([leadRow(7, 'Omar')], 'Careers: apply'),
            leadSheet([leadRow(8, 'Mona')], 'Lead form'),
        ]),
        exportScratchPath(),
    );

    $archive = new ZipArchive();
    $archive->open($file->path);
    $names = [];
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $names[] = $archive->getNameIndex($index);
    }

    expect($file->extension)->toBe('zip')
        ->and($file->contentType)->toBe('application/zip')
        ->and($names)->toBe(['Lead form.csv', 'Careers apply.csv', 'Lead form (2).csv'])
        ->and($archive->getFromName('Careers apply.csv'))->toBe(
            CSV_BYTE_ORDER_MARK . "ID,Name,Submitted\r\n" . "7,Omar,\"2026-10-07 12:30\"\r\n",
        );

    $archive->close();
});

it('reads a sheet’s rows once, as they are produced', function () {
    $rows = (static function (): Generator {
        yield leadRow(41, 'Salma');
        yield leadRow(42, 'Omar');
    })();

    $file = (new CsvExportWriter())->write(
        new ExportDocument('Leads', [new ExportSheet('Leads', ['ID', 'Name', 'Submitted'], $rows)]),
        exportScratchPath(),
    );

    expect(substr_count((string) file_get_contents($file->path), "\r\n"))->toBe(3);
});
