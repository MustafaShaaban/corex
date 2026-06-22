<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * Reads stored form submissions for the Data screen. Split from SubmissionsSource so the
 * row-shaping is unit-testable with a stub while the WP_Query/meta access stays at the
 * boundary (WpSubmissionsReader).
 */
interface SubmissionsReader
{
    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function page(int $page, int $perPage): array;

    public function total(): int;

    public function trash(int $id): bool;

    /**
     * The records matching a query (search / form filter / sort / pagination), spec 045.
     *
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function query(DataQuery $query): array;

    /** The total records matching a query (for pagination). */
    public function count(DataQuery $query): int;

    /**
     * One record by id, or null if absent.
     *
     * @return array{id:int,date:string,form:string,fields:array<string,mixed>}|null
     */
    public function find(int $id): ?array;

    /**
     * The distinct submitted field keys seen across a sample of recent submissions — the
     * basis for the derived schema. Order is first-seen.
     *
     * @return list<string>
     */
    public function fieldKeys(int $sample): array;

    /**
     * Real submission counts keyed by `Y-m-d` for the last $days days (sparse — only days
     * that have submissions appear; the caller zero-fills the rest).
     *
     * @return array<string,int>
     */
    public function dailyCounts(int $days): array;
}
