<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * A manageable data set behind the Corex → Data admin screen. Form submissions are the
 * reference implementation; any Corex custom table (TableRepository) can implement this to
 * appear in the same DataViews table with no new UI code (spec 030).
 */
interface DataSource
{
    /** URL-safe key, e.g. `submissions`. */
    public function key(): string;

    /** Human label for the source switcher. */
    public function label(): string;

    /**
     * @return list<array{id:string,label:string}> the table columns
     */
    public function columns(): array;

    /**
     * @return list<array<string,scalar>> one row per record, keyed by column id
     */
    public function rows(int $page, int $perPage): array;

    /** Total record count (for pagination). */
    public function total(): int;

    /** Remove one record; false if not found or not permitted. */
    public function delete(int $id): bool;
}
