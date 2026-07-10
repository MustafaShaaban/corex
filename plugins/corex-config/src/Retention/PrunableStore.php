<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * One data store that participates in the unified retention sweep (spec 068 T202): activity events,
 * captured email, consent records, export logs, and the like. Each reports a stable key + human
 * label, its configured retention window in days (0 = keep forever), and prunes rows older than a
 * cutoff, returning how many were removed. Keeping this a small seam lets the sweep stay pure and
 * unit-testable while each real store owns its own storage.
 */
interface PrunableStore
{
    public function key(): string;

    public function label(): string;

    /** The retention window in days; 0 means "keep forever" (never pruned by the sweep). */
    public function retentionDays(): int;

    /** Delete rows older than $cutoff and return the number removed. */
    public function pruneOlderThan(DateTimeImmutable $cutoff): int;
}
