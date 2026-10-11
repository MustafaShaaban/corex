<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * The `$wpdb` side of the release installer's table copies (spec 107, FR-034).
 *
 * The names that reach this are worked out by `ReleaseTableCopies` from tables the schema
 * registry declares, never from a request. Each is still held to a strict pattern before it
 * is used, and written into its statement as an identifier (`%i`), so a name that is not one
 * does nothing.
 */
final class WpReleaseTableCopier implements ReleaseTableCopier
{
    private const A_NAME = '/^[A-Za-z0-9_]{1,64}$/';

    public function tablesStartingWith(string $prefix): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- asked once, when the Releases screen opens or an installation runs.
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($prefix) . '%'));

        return array_values(array_map('strval', (array) $tables));
    }

    public function exists(string $table): bool
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    public function copy(string $from, string $to): bool
    {
        global $wpdb;

        if (! self::isAName($from) || ! self::isAName($to) || $this->exists($to)) {
            return false;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- a copy of a table is made once an installation, and is a change to the schema on purpose.
        $whole = $wpdb->query($wpdb->prepare('CREATE TABLE %i LIKE %i', $to, $from)) !== false
            && $wpdb->query($wpdb->prepare('INSERT INTO %i SELECT * FROM %i', $to, $from)) !== false
            // A host that cuts the request off mid-copy is not here to answer. A copy that
            // came out short for any other reason is, and is not kept.
            && (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $to)) === (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $from));
        // phpcs:enable

        if (! $whole) {
            $this->drop($to);
        }

        return $whole;
    }

    public function drop(string $table): void
    {
        global $wpdb;

        if (! self::isAName($table)) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- only ever a copy this class's caller named.
        $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
    }

    private static function isAName(string $name): bool
    {
        return preg_match(self::A_NAME, $name) === 1;
    }
}
