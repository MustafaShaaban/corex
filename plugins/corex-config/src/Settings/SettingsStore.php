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

    /**
     * A secret (a password-typed field) is saved with autoload off (spec 129, FR-006): only the code
     * that uses the key reads it, so it has no business in the memory of every request. Anything else
     * is saved as before, leaving autoload to WordPress.
     */
    public function save(string $key, string $value, bool $secret = false): void
    {
        update_option($this->optionName($key), $value, $secret ? false : null);
    }

    /** Takes the stored value away, so the setting falls back to its default (spec 119, US-SET-15). */
    public function delete(string $key): void
    {
        delete_option($this->optionName($key));
    }
}
