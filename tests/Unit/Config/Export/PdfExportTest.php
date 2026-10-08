<?php

/**
 * An export as a document to file (spec 103, US8).
 *
 * The owner asked for a PDF "signed off with the identity", and said what he meant: "use a corex
 * signature ... pdf exported should has the corex logo on it as it has the copyrights of the
 * tool". The layout is tested here as the HTML it hands the PDF library. A document is written
 * for real in the integration suite and not here: this suite's harness wraps PHP's file streams,
 * and through that wrapper the library's reads of a font come back short.
 *
 * @package Corex\Tests\Unit\Config\Export
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Export\ExportCell;
use Corex\Config\Export\ExportDirectory;
use Corex\Config\Export\ExportDocument;
use Corex\Config\Export\ExportSheet;
use Corex\Config\Export\ExportWriters;
use Corex\Config\Export\PdfExportLayout;
use Corex\Config\Export\PdfExportWriter;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    Functions\when('esc_attr')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
});

function pdfLayout(bool $rightToLeft = false, string $site = 'Muva'): PdfExportLayout
{
    return new PdfExportLayout(
        static fn (): string => $site,
        static fn (): bool => $rightToLeft,
        dirname(__DIR__, 4) . '/plugins/corex-config/assets/brand/corex-lockup-print.png',
        static fn (): DateTimeImmutable => new DateTimeImmutable('2026-10-08 12:00:00 UTC'),
    );
}

/** @param list<list<string|int>> $rows */
function pdfSheet(string $name, array $headings, array $rows): ExportSheet
{
    return new ExportSheet($name, $headings, array_map(
        static fn (array $row): array => array_map(
            static fn (string|int $value): ExportCell => is_int($value) ? ExportCell::number($value) : ExportCell::text($value),
            $row,
        ),
        $rows,
    ));
}

function pdfDocument(?ExportSheet $sheet = null): ExportDocument
{
    return new ExportDocument(
        'Contact submissions',
        [$sheet ?? pdfSheet('Contact', ['ID', 'Name', 'Message'], [[41, 'Salma', 'Hello']])],
        [['What was exported', 'Status: New'], ['Records', '1'], ['Exported by', 'Mustafa Shaaban, 2026-10-08 15:40']],
    );
}

/** Every chunk the layout hands over for a sheet, as one string. */
function pdfSheetHtml(PdfExportLayout $layout, ExportSheet $sheet, bool $named = false): string
{
    return implode('', [...$layout->sheet($sheet, $named)]);
}

it('heads every page with the site’s name and the document’s title', function () {
    $header = pdfLayout()->header(pdfDocument());

    expect($header)->toContain('Muva')->toContain('Contact submissions');
});

it('signs every page as CoreX’s: its logo, its copyright line, and the page’s number of how many', function () {
    $footer = pdfLayout()->footer();

    expect($footer)->toContain('corex-lockup-print.png')
        ->toContain('alt="CoreX"')
        ->toContain('Exported with CoreX.')
        ->toContain('&copy; 2026 CoreX.')
        // The PDF library puts the numbers in where these stand.
        ->toContain('{PAGENO}')->toContain('{nbpg}');
});

it('opens with the title and what a reader is told about the export', function () {
    $opening = pdfLayout()->opening(pdfDocument());

    expect($opening)->toContain('<h1>Contact submissions</h1>')
        ->toContain('What was exported')->toContain('Status: New')
        ->toContain('Records')
        ->toContain('Exported by')->toContain('Mustafa Shaaban, 2026-10-08 15:40');
});

it('lays a sheet out as a table whose heading row is repeated on every page', function () {
    $html = pdfSheetHtml(pdfLayout(), pdfSheet('Contact', ['ID', 'Name', 'Message'], [[41, 'Salma', 'Hello'], [42, 'Omar', 'Hi']]));

    expect($html)->toContain('repeat_header="1"')
        ->toContain('<thead>')
        ->toContain('<th dir="ltr">ID</th>')->toContain('<th dir="ltr">Message</th>')
        ->toContain('>41<')->toContain('>Salma<')->toContain('>Hi<')
        ->and(substr_count($html, '<tr'))->toBe(3);
});

it('names a sheet only when the document holds more than one', function () {
    $sheet = pdfSheet('Contact', ['ID'], [[1]]);

    expect(pdfSheetHtml(pdfLayout(), $sheet, named: true))->toContain('<h2>Contact</h2>')
        ->and(pdfSheetHtml(pdfLayout(), $sheet))->not->toContain('<h2>');
});

it('gives each cell the direction of what it holds, so a number or an address keeps its order on a right-to-left page', function (string $text, string $direction) {
    $html = pdfSheetHtml(pdfLayout(rightToLeft: true), pdfSheet('Contact', ['Value'], [[$text]]));

    expect($html)->toContain('<td dir="' . $direction . '">');
})->with([
    'Arabic' => ['أريد موقعاً لشركتي.', 'rtl'],
    'Hebrew' => ['שלום', 'rtl'],
    'English' => ['A website, please.', 'ltr'],
    'a phone number' => ['+20 101 699 9700', 'ltr'],
    'a date and time' => ['2026-10-07 12:30', 'ltr'],
    'English that quotes Arabic' => ['Mixed: نريد عرض سعر for 3 pages.', 'ltr'],
    'Arabic that begins with a number' => ['3 صفحات', 'rtl'],
    'nothing' => ['', 'ltr'],
]);

it('writes what a visitor typed as text, never as markup', function () {
    $html = pdfSheetHtml(pdfLayout(), pdfSheet('Contact', ['Message'], [['<img src=x onerror=alert(1)> & co']]));

    expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt; &amp; co')
        ->not->toContain('<img src=x');
});

it('lays out a sheet too wide for a page as one block per record, each question beside its answer', function () {
    $headings = array_map(static fn (int $n): string => 'Question ' . $n, range(1, 12));
    $html = pdfSheetHtml(pdfLayout(), pdfSheet('Survey', $headings, [range(1, 12), range(101, 112)]));

    expect($html)->not->toContain('<thead>')
        // One block per record, kept on one page: an answer is never parted from its question.
        ->and(substr_count($html, 'class="record"'))->toBe(2)
        ->and(pdfLayout()->styles())->toMatch('/table\.record \{[^}]*page-break-inside: avoid/')
        ->and($html)->toContain('<th dir="ltr">Question 12</th>')
        ->and($html)->toContain('>112<');
});

it('sets a right-to-left page from the end it is read from', function () {
    expect(pdfLayout(rightToLeft: true)->styles())->toContain('text-align: right')
        // The space between a label and what it says is on the label's far side.
        ->toContain('padding-left: 4mm')
        ->and(pdfLayout()->styles())->toContain('text-align: left')->toContain('padding-right: 4mm');
});

it('keeps English in its own order on a right-to-left page, in the header, the footer and the labels', function () {
    $layout = pdfLayout(rightToLeft: true, site: 'موفا');

    expect($layout->header(pdfDocument()))->toContain('<td dir="rtl"')->toContain('<td dir="ltr"')
        ->and($layout->footer())->not->toContain('dir="rtl"')
        ->and($layout->opening(pdfDocument()))->toContain('<th dir="ltr">What was exported</th>');
});

it('offers PDF among the formats only when it can be written', function () {
    $directory = new class implements ExportDirectory {
        public function path(): string
        {
            return sys_get_temp_dir();
        }
    };
    $with = new ExportWriters(static fn (): bool => false, new PdfExportWriter(pdfLayout(), $directory));
    $without = new ExportWriters(static fn (): bool => false);

    expect($without->available())->toBe(['xlsx', 'csv'])
        ->and(fn () => $without->for('pdf'))->toThrow(InvalidArgumentException::class)
        ->and($with->available())->toBe(PdfExportWriter::supported() ? ['xlsx', 'csv', 'pdf'] : ['xlsx', 'csv']);
});
