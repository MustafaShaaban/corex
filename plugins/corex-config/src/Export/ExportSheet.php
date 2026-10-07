<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

/**
 * One table of an export: a name, its headings, and its rows.
 *
 * The rows are read once, in order. They may be produced as they are read, so a writer must not
 * count them or go back.
 */
final readonly class ExportSheet
{
    /**
     * @param list<string>               $headings
     * @param iterable<list<ExportCell>> $rows     One cell per heading, in the same order.
     */
    public function __construct(public string $name, public array $headings, public iterable $rows)
    {
    }
}
