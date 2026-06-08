<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config\Sources;

defined('ABSPATH') || exit;

use Corex\Support\Config\Source;

/**
 * The middle layer: WordPress options. A dot key maps to a prefixed option name
 * (`app.name` → `corex_app_name`).
 */
final class OptionsSource implements Source
{
    private const PREFIX = 'corex_';

    private const SENTINEL = "\0corex_option_unset\0";

    public function has(string $key): bool
    {
        return get_option($this->optionName($key), self::SENTINEL) !== self::SENTINEL;
    }

    public function get(string $key): mixed
    {
        return get_option($this->optionName($key));
    }

    private function optionName(string $key): string
    {
        return self::PREFIX . str_replace('.', '_', $key);
    }
}
