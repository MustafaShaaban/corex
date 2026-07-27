<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;

/**
 * The `$wpdb` implementation of the managed-table migration boundary.
 *
 * Two things are worth stating plainly about the SQL here. First, the plan statements come from a
 * `ManagedTable` declaration — code the site installed, never request data — and the only value
 * substituted into them is the table's own prefixed name, which the Migrator derives. Second,
 * identifiers cannot be bound with `%i` inside a DDL statement the way values can, so table names
 * are validated against a strict pattern before they are interpolated and rejected otherwise.
 */
final class WpMigrationTableRunner implements TableMigrationRunner
{
    /** Snapshot names are derived, never supplied, so this pattern is a tripwire rather than a filter. */
    private const IDENTIFIER = '/^[A-Za-z0-9_]+$/';

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function snapshot(string $table): string
    {
        global $wpdb;

        $source = $this->identifier($this->migrator->fullName($table));

        if ($source === '') {
            return '';
        }

        $snapshot = $this->identifier($source . '_snap_' . gmdate('YmdHis') . '_' . wp_rand(1000, 9999));

        if ($snapshot === '') {
            return '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- DDL cannot bind identifiers; both names are derived and pattern-validated above.
        $created = $wpdb->query("CREATE TABLE `{$snapshot}` LIKE `{$source}`");

        if ($created === false) {
            return '';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
        $copied = $wpdb->query("INSERT INTO `{$snapshot}` SELECT * FROM `{$source}`");

        if ($copied === false) {
            $this->discard($snapshot);

            return '';
        }

        return $snapshot;
    }

    /** @param list<string> $statements */
    public function runPlan(string $table, array $statements): int
    {
        global $wpdb;

        $target = $this->identifier($this->migrator->fullName($table));

        if ($target === '') {
            return 0;
        }

        $ran = 0;

        foreach ($statements as $statement) {
            $sql = str_replace('{table}', '`' . $target . '`', (string) $statement);

            if (trim($sql) === '') {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- the statement is a code-declared migration plan, not request data.
            if ($wpdb->query($sql) === false) {
                // Stop at the first failure: continuing would apply half a plan and leave the
                // table in a state no snapshot describes.
                break;
            }

            $ran++;
        }

        return $ran;
    }

    public function restore(string $table, string $snapshot): bool
    {
        global $wpdb;

        $target   = $this->identifier($this->migrator->fullName($table));
        $snapshot = $this->identifier($snapshot);

        if ($target === '' || $snapshot === '') {
            return false;
        }

        // Swap rather than copy back: this restores the schema the migration changed as well as
        // the rows, which is what "rolled back" has to mean for it to be worth offering.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- derived, pattern-validated identifiers; DDL cannot bind them.
        $dropped = $wpdb->query("DROP TABLE IF EXISTS `{$target}`");

        if ($dropped === false) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- as above.
        return $wpdb->query("RENAME TABLE `{$snapshot}` TO `{$target}`") !== false;
    }

    public function discard(string $snapshot): void
    {
        global $wpdb;

        $snapshot = $this->identifier($snapshot);

        if ($snapshot === '') {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- derived, pattern-validated identifier.
        $wpdb->query("DROP TABLE IF EXISTS `{$snapshot}`");
    }

    private function identifier(string $name): string
    {
        return preg_match(self::IDENTIFIER, $name) === 1 ? $name : '';
    }
}
