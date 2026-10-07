<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

/**
 * Writes an export in one format.
 */
interface ExportWriter
{
    /**
     * @param string $pathWithoutExtension Where to write. The writer adds the extension, which
     *                                     depends on what the document holds.
     */
    public function write(ExportDocument $document, string $pathWithoutExtension): ExportFile;
}
