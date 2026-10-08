<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

/**
 * What an export holds, before any format: a title, one sheet per table, and what a reader is
 * told about the export. A format with pages prints the title and the facts; a workbook and a CSV
 * have nowhere to, and leave them out.
 */
final readonly class ExportDocument
{
    /**
     * @param list<ExportSheet>                $sheets
     * @param list<array{0:string,1:string}>   $facts  A label and what it says, in reading order:
     *                                                 what was exported, how many, by whom and when.
     */
    public function __construct(public string $title, public array $sheets, public array $facts = [])
    {
    }
}
