<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * WordPress options are site-scoped by construction: under switch_to_blog(),
 * every call targets that site's options table (spec 100 FR-029, FR-034).
 *
 * The versions are stored with autoload off and stay so. No front-end request reads
 * them, and an admin or cron request reads them in one statement however many
 * components there are, so autoloading them would save that one statement by loading
 * them everywhere else. And WordPress leaves the autoload of a stored option alone
 * when the value written is the one it has, so a site that holds them today would
 * need a migration of its own to move them (DECISIONS #360).
 */
final class WpOptionSchemaVersionStore implements SchemaVersionStore
{
    public function get(string $optionName): string
    {
        return (string) get_option($optionName, '');
    }

    public function getMany(array $optionNames): array
    {
        // One SELECT for the names WordPress has not read yet, and none where its object
        // cache outlives the request. A name that is not stored is remembered as missing.
        wp_prime_option_caches($optionNames);

        return array_combine($optionNames, array_map($this->get(...), $optionNames));
    }

    public function forget(array $optionNames): void
    {
        wp_cache_delete_multiple($optionNames, 'options');

        // WordPress keeps the names it found no row for in one entry, and answers from it
        // before it asks the database.
        $missing = wp_cache_get('notoptions', 'options');

        if (! is_array($missing)) {
            return;
        }

        $stillMissing = array_diff_key($missing, array_flip($optionNames));

        if (count($stillMissing) !== count($missing)) {
            wp_cache_set('notoptions', $stillMissing, 'options');
        }
    }

    public function put(string $optionName, string $version): void
    {
        update_option($optionName, $version, false);
    }
}
