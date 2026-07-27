<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * The write half of the managed-table boundary, kept separate from {@see TableDataReader} so a
 * read-only table's source cannot be handed something that writes.
 *
 * Every method takes the columns it is allowed to touch as an argument rather than trusting the
 * values array: the caller has already narrowed them to the table's declared writable fields, and
 * passing them explicitly means the `$wpdb` layer can drop anything else without knowing why.
 *
 * @see WpTableDataWriter for the prepared, bounded implementation.
 */
interface TableDataWriter
{
    /**
     * @param  list<string>        $columns the columns that may be written
     * @param  array<string,mixed> $values
     * @return int                 the new row id, or 0 when the insert failed
     */
    public function insert(string $table, array $columns, array $values): int;

    /**
     * @param  list<string>        $columns the columns that may be written
     * @param  list<int>           $ids
     * @param  array<string,mixed> $values
     * @return int                 how many rows were updated
     */
    public function update(string $table, array $columns, array $ids, array $values): int;

    /**
     * @param  list<int> $ids
     * @return int       how many rows were deleted
     */
    public function delete(string $table, array $ids): int;
}
