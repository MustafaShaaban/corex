<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

/**
 * The schema-changing boundary for a managed table's declared migrations (spec 074, FR-3.6).
 *
 * Snapshot-then-swap rather than snapshot-then-replay: the snapshot is a real copy of the table as
 * it was, and rolling back puts that copy back in place. That restores the schema *and* the rows,
 * which a replayed "down" script generally cannot promise — and it is why the rollback claim in the
 * interface is one CoreX can actually keep.
 *
 * @see WpTableMigrationRunner for the `$wpdb` implementation.
 */
interface TableMigrationRunner
{
    /**
     * Copy the table as it stands and return the snapshot's identifier.
     *
     * @return string the snapshot table name, or '' when the copy could not be made
     */
    public function snapshot(string $table): string;

    /**
     * Run a declared plan against the table.
     *
     * Statements use `{table}` where the real, prefixed table name belongs; the implementation
     * substitutes it, so a declaration never has to know the site's table prefix.
     *
     * @param  list<string> $statements
     * @return int          how many statements ran successfully
     */
    public function runPlan(string $table, array $statements): int;

    /** Put a snapshot back in place of the table, schema and rows together. */
    public function restore(string $table, string $snapshot): bool;

    /** Drop a snapshot that is no longer needed. */
    public function discard(string $snapshot): void;
}
