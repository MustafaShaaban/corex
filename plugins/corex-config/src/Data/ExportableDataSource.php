<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * A source that can hand its records to an export (spec 103, US10).
 *
 * A source has three shapes, and only the source knows them apart: the row its table shows, keyed
 * by column ({@see QueryableDataSource::query()}); the record its detail view shows
 * ({@see QueryableDataSource::record()}); and the row an export writes, which is this one. An
 * export offers a column for every field the source declares and reads each cell from the row by
 * the field's key, so an export row carries a value under every key {@see fields()} declares.
 *
 * The export used to take the table's rows for a query and the detail view's record for a ticked
 * row. Form submissions declare a field for each answer and show a summary in their table, so
 * every answer's column was written empty, and a ticked row lost its summary as well.
 */
interface ExportableDataSource extends QueryableDataSource, FieldAwareDataSource
{
    /**
     * The matching page of records, each as an export row.
     *
     * @return list<array<string,mixed>> Each keyed by field key; '' where a record has no value.
     */
    public function exportRows(DataQuery $query): array;

    /**
     * These records, each as the same export row, in the order asked for. One that no longer
     * exists is left out.
     *
     * @param list<int> $ids
     *
     * @return list<array<string,mixed>>
     */
    public function exportRowsOf(array $ids): array;
}
