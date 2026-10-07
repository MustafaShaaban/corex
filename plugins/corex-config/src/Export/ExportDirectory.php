<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

/**
 * Where exported files are written.
 */
interface ExportDirectory
{
    /**
     * @return string An absolute path that exists and can be written, with no trailing slash.
     *
     * @throws \RuntimeException When there is nowhere to write.
     */
    public function path(): string;
}
