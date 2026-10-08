<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use Closure;
use DateTimeImmutable;

/**
 * An export as a document to file, as the HTML a PDF library is handed (spec 103, US8).
 *
 * Every page is headed by the site's name and the document's title, and signed as CoreX's: its
 * logo, a copyright line, and the page's number of how many. The owner, of what "signed off with
 * the identity" meant: "pdf exported should has the corex logo on it as it has the copyrights of
 * the tool".
 *
 * The colours are ink on paper, written out: a PDF has no stylesheet of the site's to read them
 * from.
 */
final readonly class PdfExportLayout
{
    /** More columns than this do not fit a page as a table; the records are set out one by one. */
    public const MOST_TABLE_COLUMNS = 8;

    private const INK = '#14151a';
    private const MUTED = '#5b616d';
    private const RULE = '#d9d9de';
    private const BRASS = '#ad8643';
    private const TINT = '#f4f0e7';

    /** Letters of the scripts that are read from the right. */
    private const RIGHT_TO_LEFT_LETTER = '/^[\p{Arabic}\p{Hebrew}\p{Syriac}\p{Thaana}]/u';

    /**
     * @param Closure():string            $siteName
     * @param Closure():bool              $rightToLeft   Whether the site reads right to left.
     * @param string                      $signatureFile The CoreX lockup as an image a page can print.
     * @param Closure():DateTimeImmutable $now
     */
    public function __construct(
        private Closure $siteName,
        private Closure $rightToLeft,
        private string $signatureFile,
        private Closure $now,
    ) {
    }

    public function rightToLeft(): bool
    {
        return ($this->rightToLeft)();
    }

    public function header(ExportDocument $document): string
    {
        return sprintf(
            '<table width="100%%" style="border-bottom: 0.3mm solid %s; font-size: 9pt; color: %s;"><tr>'
            . '<td dir="%s" style="font-size: 12pt; font-weight: bold; text-align: %s;">%s</td>'
            . '<td dir="%s" style="text-align: %s; color: %s;">%s</td></tr></table>',
            self::BRASS,
            self::INK,
            $this->directionOf(($this->siteName)()),
            $this->start(),
            esc_html(($this->siteName)()),
            $this->directionOf($document->title),
            $this->end(),
            self::MUTED,
            esc_html($document->title),
        );
    }

    /**
     * CoreX's signature, and where the page stands. `{PAGENO}` and `{nbpg}` are the PDF library's
     * names for the two numbers; it puts them in.
     */
    public function footer(): string
    {
        $signature = __('Exported with CoreX.', 'corex');
        /* translators: 1: this page's number. 2: how many pages the document has. */
        $page = sprintf(__('Page %1$s of %2$s', 'corex'), '{PAGENO}', '{nbpg}');

        return sprintf(
            '<table width="100%%" style="border-top: 0.2mm solid %s; font-size: 8pt; color: %s;"><tr>'
            . '<td dir="%s" style="text-align: %s;"><img src="%s" alt="CoreX" style="height: 4.2mm; vertical-align: middle;" />'
            . ' &nbsp; %s &copy; %s CoreX.</td>'
            . '<td dir="%s" style="text-align: %s;">%s</td></tr></table>',
            self::RULE,
            self::MUTED,
            $this->directionOf($signature),
            $this->start(),
            esc_attr($this->signatureFile),
            esc_html($signature),
            ($this->now)()->format('Y'),
            $this->directionOf($page),
            $this->end(),
            esc_html($page),
        );
    }

    public function styles(): string
    {
        $start = $this->start();

        return '<style>'
            . 'body { color: ' . self::INK . '; }'
            . 'h1 { font-size: 16pt; margin: 0 0 3mm; }'
            . 'h2 { font-size: 11pt; margin: 6mm 0 2mm; }'
            . 'table.about { font-size: 9pt; margin-bottom: 5mm; }'
            . 'table.about th { color: ' . self::MUTED . '; font-weight: normal; text-align: ' . $start . '; padding: 0.6mm 0; padding-' . $this->end() . ': 4mm; vertical-align: top; }'
            . 'table.about td { text-align: ' . $start . '; padding: 0.6mm 0; }'
            . 'table.records { border-collapse: collapse; width: 100%; }'
            . 'table.records th { background: ' . self::TINT . '; text-align: ' . $start . '; padding: 1.5mm 2mm; border-bottom: 0.3mm solid ' . self::INK . '; font-size: 8.5pt; }'
            . 'table.records td { text-align: ' . $start . '; padding: 1.5mm 2mm; border-bottom: 0.15mm solid ' . self::RULE . '; vertical-align: top; }'
            . 'table.records tr { page-break-inside: avoid; }'
            . 'table.record { border-collapse: collapse; width: 100%; margin-bottom: 4mm; page-break-inside: avoid; }'
            . 'table.record th { width: 32%; background: ' . self::TINT . '; text-align: ' . $start . '; padding: 1.2mm 2mm; border-bottom: 0.15mm solid ' . self::RULE . '; font-size: 8.5pt; vertical-align: top; }'
            . 'table.record td { text-align: ' . $start . '; padding: 1.2mm 2mm; border-bottom: 0.15mm solid ' . self::RULE . '; vertical-align: top; }'
            . '</style>';
    }

    /**
     * The title, and what a reader is told about the export before its records.
     */
    public function opening(ExportDocument $document): string
    {
        $facts = '';
        foreach ($document->facts as [$label, $value]) {
            $facts .= '<tr>' . $this->heading($label) . $this->cell($value) . '</tr>';
        }

        return '<h1>' . esc_html($document->title) . '</h1>'
            . ($facts === '' ? '' : '<table class="about">' . $facts . '</table>');
    }

    /**
     * One sheet, in pieces the PDF library takes one at a time. Each piece is whole: a table is
     * never left open between two of them.
     *
     * @param bool $named Whether to head the sheet with its name: a document of one sheet has its
     *                    title, and needs no second heading.
     *
     * @return iterable<string>
     */
    public function sheet(ExportSheet $sheet, bool $named): iterable
    {
        if ($named) {
            yield '<h2>' . esc_html($sheet->name) . '</h2>';
        }

        if (count($sheet->headings) > self::MOST_TABLE_COLUMNS) {
            yield from $this->records($sheet);

            return;
        }

        $rows = '';
        foreach ($sheet->rows as $cells) {
            $rows .= '<tr>' . implode('', array_map(fn (ExportCell $cell): string => $this->cell($cell->asText()), $cells)) . '</tr>';
        }

        yield '<table class="records" repeat_header="1"><thead><tr>'
            . implode('', array_map($this->heading(...), $sheet->headings))
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * A sheet too wide for a page: each record is a block of its own, a question beside its
     * answer, and a block is kept on one page.
     *
     * @return iterable<string>
     */
    private function records(ExportSheet $sheet): iterable
    {
        foreach ($sheet->rows as $cells) {
            $lines = '';
            foreach ($cells as $index => $cell) {
                $lines .= '<tr>' . $this->heading($sheet->headings[$index] ?? '') . $this->cell($cell->asText()) . '</tr>';
            }

            yield '<table class="record">' . $lines . '</table>';
        }
    }

    /**
     * A cell in the direction of what it holds. On a right-to-left page a phone number, a date or
     * an English sentence was reordered: "+20 101 699 9700" read "9700 699 101 20+".
     */
    private function cell(string $text): string
    {
        return '<td dir="' . $this->directionOf($text) . '">' . esc_html($text) . '</td>';
    }

    /** A heading or a label, in the direction of what it says, as a cell is. */
    private function heading(string $text): string
    {
        return '<th dir="' . $this->directionOf($text) . '">' . esc_html($text) . '</th>';
    }

    /**
     * The direction of a text, by its first letter: digits and punctuation before it say nothing.
     */
    private function directionOf(string $text): string
    {
        $letters = (string) preg_replace('/^[^\p{L}]+/u', '', $text);

        return preg_match(self::RIGHT_TO_LEFT_LETTER, $letters) === 1 ? 'rtl' : 'ltr';
    }

    private function start(): string
    {
        return $this->rightToLeft() ? 'right' : 'left';
    }

    private function end(): string
    {
        return $this->rightToLeft() ? 'left' : 'right';
    }
}
