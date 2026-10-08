<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use Closure;
use InvalidArgumentException;

/**
 * The formats an export can be written in, and the writer for each.
 */
final readonly class ExportWriters
{
    public const CSV = 'csv';
    public const XLSX = 'xlsx';
    public const PDF = 'pdf';

    public const FORMATS = [self::CSV, self::XLSX, self::PDF];

    /**
     * The most records a PDF is written for. It is written in the last step of an export, in one
     * request, and its cost grows faster than its rows: 500 took 7 seconds and 68MB where 1,000
     * took 17 seconds and 116MB (spec 103, plan D3). A larger export is a workbook.
     */
    public const PDF_MOST_RECORDS = 500;

    /** A separator by the name a request gives it. */
    public const SEPARATORS = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t"];

    public const DEFAULT_SEPARATOR = 'comma';

    /**
     * @param Closure():bool       $rightToLeft Whether the site reads right to left. Asked when a
     *                                          file is written: it follows the site's language,
     *                                          which is not settled when this is built.
     * @param PdfExportWriter|null $pdf         The writer of PDFs; without one, PDF is not offered.
     */
    public function __construct(private Closure $rightToLeft, private ?PdfExportWriter $pdf = null)
    {
    }

    /**
     * The formats an export can be written in here, in the order they are offered. PDF is among
     * them only where it can be written: it needs a library and two PHP extensions.
     *
     * @return list<string>
     */
    public function available(): array
    {
        return $this->pdf !== null && PdfExportWriter::supported()
            ? [self::XLSX, self::CSV, self::PDF]
            : [self::XLSX, self::CSV];
    }

    /**
     * What a dialog is told about the formats: which can be written here, and the most a PDF holds.
     *
     * @return array{available:list<string>,pdf_most_records:int}
     */
    public function describe(): array
    {
        return ['available' => $this->available(), 'pdf_most_records' => self::PDF_MOST_RECORDS];
    }

    /**
     * @param string $separator One of the names in {@see self::SEPARATORS}. Only CSV reads it.
     */
    public function for(string $format, string $separator = self::DEFAULT_SEPARATOR): ExportWriter
    {
        return match ($format) {
            self::CSV => new CsvExportWriter(
                self::SEPARATORS[$separator] ?? self::SEPARATORS[self::DEFAULT_SEPARATOR],
            ),
            self::XLSX => new XlsxExportWriter($this->rightToLeft),
            self::PDF => $this->pdf ?? throw new InvalidArgumentException('PDF export is not set up here.'),
            default => throw new InvalidArgumentException(sprintf('There is no export writer for "%s".', $format)),
        };
    }
}
