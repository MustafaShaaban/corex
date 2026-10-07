<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

/**
 * A written export: where it is, and what to tell a browser it is.
 */
final readonly class ExportFile
{
    public function __construct(public string $path, public string $extension, public string $contentType)
    {
    }
}
