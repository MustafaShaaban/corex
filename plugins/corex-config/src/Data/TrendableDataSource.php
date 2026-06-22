<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * An optional capability: a data source that can report a real per-day record count over a
 * recent window, so the Data screen can draw a truthful activity chart. Days with no records
 * report zero (never a fabricated value). Sources that do not implement it show a designed
 * empty chart state instead.
 */
interface TrendableDataSource
{
    /**
     * Per-day counts for the last $days days, oldest first, every day present (zero-filled).
     *
     * @return list<array{date:string,count:int}>
     */
    public function trend(int $days): array;
}
