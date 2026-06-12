<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;

/**
 * The `$wpdb` boundary for a managed custom table (spec 038). Every query is **prepared** —
 * identifiers with `%i`, the id with `%d` — and the page read is **bounded** with `LIMIT/OFFSET`
 * (never an unbounded scan). The table + column names are code-defined (from a `ManagedTable`),
 * not request data; `%i` is belt-and-suspenders. The Migrator resolves the full, prefixed name.
 */
final class WpTableDataReader implements TableDataReader
{
    public function __construct(private readonly Migrator $migrator)
    {
    }

    /**
     * @param list<string> $columns
     *
     * @return list<array<string,scalar>>
     */
    public function page(string $table, array $columns, int $page, int $perPage): array
    {
        global $wpdb;

        $select       = array_merge(['id'], $columns);
        $placeholders = implode(', ', array_fill(0, count($select), '%i'));
        $offset       = ($page - 1) * $perPage;

        $sql  = "SELECT {$placeholders} FROM %i ORDER BY id DESC LIMIT %d OFFSET %d";
        $args = array_merge($select, [$this->migrator->fullName($table), $perPage, $offset]);

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);

        return is_array($rows) ? array_values($rows) : [];
    }

    public function total(string $table): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM %i', $this->migrator->fullName($table))
        );
    }

    public function delete(string $table, int $id): bool
    {
        global $wpdb;

        $deleted = $wpdb->query(
            $wpdb->prepare('DELETE FROM %i WHERE id = %d', $this->migrator->fullName($table), $id)
        );

        return is_int($deleted) && $deleted > 0;
    }
}
