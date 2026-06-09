<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Creates/drops custom tables under the site prefix + a `corex_` namespace, via
 * WordPress's idempotent `dbDelta`. The boundary to the schema. Table names are
 * code-defined (never request input), so identifier interpolation is safe here.
 */
final class Migrator
{
    private const NAMESPACE = 'corex_';

    public function fullName(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . self::NAMESPACE . $name;
    }

    public function create(Table $table): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($table->createSql($this->fullName($table->name), $wpdb->get_charset_collate()));
    }

    public function drop(string $name): void
    {
        global $wpdb;

        // $name is code-defined; the resulting identifier is not request data.
        $wpdb->query('DROP TABLE IF EXISTS ' . $this->fullName($name));
    }

    public function exists(string $name): bool
    {
        global $wpdb;

        $table = $this->fullName($name);

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }
}
