<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;

/**
 * The `$wpdb` write boundary for a managed custom table (spec 074).
 *
 * Every statement is **prepared** — identifiers with `%i`, values with `%s`/`%d` — and every write
 * is confined to the columns the caller passed in, which the source has already narrowed to the
 * table's declared writable fields. A value whose column is not on that list is dropped here rather
 * than trusted, so a malformed CSV row or a mistaken mapping cannot reach a column nobody declared.
 *
 * Deletes and updates are bounded by an explicit id list; there is no path to an unqualified
 * `UPDATE`/`DELETE` over a whole table.
 */
final class WpTableDataWriter implements TableDataWriter
{
    /** Matches the import batch ceiling; an id list longer than this is a mistake, not a request. */
    private const MAX_IDS = 500;

    public function __construct(private readonly Migrator $migrator)
    {
    }

    /**
     * @param list<string>        $columns
     * @param array<string,mixed> $values
     */
    public function insert(string $table, array $columns, array $values): int
    {
        global $wpdb;

        $writable = $this->narrow($columns, $values);

        if ($writable === []) {
            return 0;
        }

        $assignments = implode(', ', array_fill(0, count($writable), '%i = %s'));
        $args        = [$this->migrator->fullName($table)];

        foreach ($writable as $column => $value) {
            $args[] = $column;
            $args[] = $value;
        }

        // `INSERT ... SET` rather than `INSERT ... VALUES` so the column identifiers can be bound
        // with %i in the same pass as the values, instead of being interpolated into the column list.
        $inserted = $wpdb->query(
            $wpdb->prepare("INSERT INTO %i SET {$assignments}", ...$args)
        );

        return is_int($inserted) && $inserted > 0 ? (int) $wpdb->insert_id : 0;
    }

    /**
     * @param list<string>        $columns
     * @param list<int>           $ids
     * @param array<string,mixed> $values
     */
    public function update(string $table, array $columns, array $ids, array $values): int
    {
        global $wpdb;

        $writable = $this->narrow($columns, $values);
        $ids      = $this->boundedIds($ids);

        if ($writable === [] || $ids === []) {
            return 0;
        }

        $assignments  = implode(', ', array_fill(0, count($writable), '%i = %s'));
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $args         = [$this->migrator->fullName($table)];

        foreach ($writable as $column => $value) {
            $args[] = $column;
            $args[] = $value;
        }

        $updated = $wpdb->query(
            $wpdb->prepare("UPDATE %i SET {$assignments} WHERE id IN ({$placeholders})", ...[...$args, ...$ids])
        );

        return is_int($updated) ? $updated : 0;
    }

    /** @param list<int> $ids */
    public function delete(string $table, array $ids): int
    {
        global $wpdb;

        $ids = $this->boundedIds($ids);

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE id IN ({$placeholders})",
                ...[$this->migrator->fullName($table), ...$ids],
            )
        );

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * Keep only the values whose column the caller declared writable, stringified for binding.
     *
     * @param  list<string>        $columns
     * @param  array<string,mixed> $values
     * @return array<string,string>
     */
    private function narrow(array $columns, array $values): array
    {
        $writable = [];

        foreach ($columns as $column) {
            if (! array_key_exists($column, $values)) {
                continue;
            }

            $value = $values[$column];
            $writable[$column] = is_scalar($value) || $value === null
                ? (string) $value
                : (string) wp_json_encode($value);
        }

        return $writable;
    }

    /**
     * @param  list<int> $ids
     * @return list<int>
     */
    private function boundedIds(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0 && ! in_array($id, $clean, true)) {
                $clean[] = $id;
            }

            if (count($clean) >= self::MAX_IDS) {
                break;
            }
        }

        return $clean;
    }
}
