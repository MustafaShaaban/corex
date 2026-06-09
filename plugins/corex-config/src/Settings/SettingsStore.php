<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * Reads/writes a setting as the prefixed option the Config engine's option layer
 * reads (`brand.logo_url` → `corex_brand_logo_url`), so saving needs no extra wiring.
 */
final class SettingsStore
{
    public function optionName(string $key): string
    {
        return 'corex_' . str_replace('.', '_', $key);
    }

    public function get(string $key, string $default = ''): string
    {
        return (string) get_option($this->optionName($key), $default);
    }

    public function save(string $key, string $value): void
    {
        update_option($this->optionName($key), $value);
    }
}
