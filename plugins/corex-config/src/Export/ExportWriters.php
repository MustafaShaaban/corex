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

    public const FORMATS = [self::CSV, self::XLSX];

    /** A separator by the name a request gives it. */
    public const SEPARATORS = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t"];

    public const DEFAULT_SEPARATOR = 'comma';

    /**
     * @param Closure():bool $rightToLeft Whether the site reads right to left. Asked when a file is
     *                                    written: it follows the site's language, which is not
     *                                    settled when this is built.
     */
    public function __construct(private Closure $rightToLeft)
    {
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
            default => throw new InvalidArgumentException(sprintf('There is no export writer for "%s".', $format)),
        };
    }
}
