<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * The data-access seam for a managed custom table — a page of rows, the total count, and a
 * delete by id. The `$wpdb` implementation ({@see WpTableDataReader}) uses prepared, bounded
 * queries; injecting this interface keeps {@see TableDataSource}'s shaping unit-testable.
 */
interface TableDataReader
{
    /**
     * @param list<string> $columns the columns to select (besides id)
     *
     * @return list<array<string,scalar>>
     */
    public function page(string $table, array $columns, int $page, int $perPage): array;

    public function total(string $table): int;

    public function delete(string $table, int $id): bool;
}
