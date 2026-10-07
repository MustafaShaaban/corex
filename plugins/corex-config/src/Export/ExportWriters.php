<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * The formats an export can be written in, and the writer for each.
 */
final class ExportWriters
{
    public const CSV = 'csv';

    public const FORMATS = [self::CSV];

    /** A separator by the name a request gives it. */
    public const SEPARATORS = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t"];

    public const DEFAULT_SEPARATOR = 'comma';

    /**
     * @param string $separator One of the names in {@see self::SEPARATORS}. Only CSV reads it.
     */
    public function for(string $format, string $separator = self::DEFAULT_SEPARATOR): ExportWriter
    {
        return match ($format) {
            self::CSV => new CsvExportWriter(
                self::SEPARATORS[$separator] ?? self::SEPARATORS[self::DEFAULT_SEPARATOR],
            ),
            default => throw new InvalidArgumentException(sprintf('There is no export writer for "%s".', $format)),
        };
    }
}
