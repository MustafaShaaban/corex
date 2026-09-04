<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * WordPress options are site-scoped by construction: under switch_to_blog(),
 * both calls target that site's options table (spec 100 FR-029, FR-034).
 */
final class WpOptionSchemaVersionStore implements SchemaVersionStore
{
    public function get(string $optionName): string
    {
        return (string) get_option($optionName, '');
    }

    public function put(string $optionName, string $version): void
    {
        update_option($optionName, $version, false);
    }
}
