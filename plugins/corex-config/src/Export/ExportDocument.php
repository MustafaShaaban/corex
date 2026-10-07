<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

/**
 * What an export holds, before any format: a title and one sheet per table.
 */
final readonly class ExportDocument
{
    /**
     * @param list<ExportSheet> $sheets
     */
    public function __construct(public string $title, public array $sheets)
    {
    }
}
