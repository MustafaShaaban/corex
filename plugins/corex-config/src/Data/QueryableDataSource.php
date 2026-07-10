<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * A {@see DataSource} that can answer a {@see DataQuery} — search / filter / sort /
 * paginate — and return a single record for the detail view (spec 045). Sources that
 * support it (form submissions) implement this; the Data screen + export use the query
 * path when a source is queryable and fall back to plain pagination otherwise (OCP — the
 * existing `DataSource` and its consumers are unchanged).
 */
interface QueryableDataSource extends DataSource
{
    /**
     * The matching page of rows for the query.
     *
     * @return list<array<string,scalar>>
     */
    public function query(DataQuery $query): array;

    /** The matching total for the query (for pagination). */
    public function count(DataQuery $query): int;

    /**
     * One record as label → value fields for the detail view, or null if absent/forbidden.
     *
     * @return array<string,mixed>|null
     */
    public function record(int $id): ?array;
}
