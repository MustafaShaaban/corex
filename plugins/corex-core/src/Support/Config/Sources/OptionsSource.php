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
 *
 * An override is an option WordPress loads with the site's options, which is how
 * `update_option()` and `wp option update` store one. Whether a key has one is read from those
 * loaded options and costs no query. Asking WordPress for each name did: it remembers that an
 * option is not stored for one request only, unless the site has a persistent object cache, so
 * every key with no override was a query on every request (DECISIONS #361).
 *
 * An option stored with autoload off is therefore not an override.
 */
final class OptionsSource implements Source
{
    private const PREFIX = 'corex_';

    private const SENTINEL = "\0corex_option_unset\0";

    public function has(string $key): bool
    {
        $option = $this->optionName($key);

        if (! $this->mayBeAnswered($option)) {
            return false;
        }

        return get_option($option, self::SENTINEL) !== self::SENTINEL;
    }

    public function get(string $key): mixed
    {
        return get_option($this->optionName($key));
    }

    /**
     * Whether WordPress could answer for the option, told without asking the database.
     */
    private function mayBeAnswered(string $option): bool
    {
        // While a site is being installed WordPress does not rely on the options it loaded, so
        // neither does this.
        if (wp_installing()) {
            return true;
        }

        if (array_key_exists($option, wp_load_alloptions())) {
            return true;
        }

        // A filter can answer for an option that is not stored.
        return has_filter('pre_option_' . $option)
            || has_filter('pre_option')
            || has_filter('default_option_' . $option);
    }

    private function optionName(string $key): string
    {
        return self::PREFIX . str_replace('.', '_', $key);
    }
}
