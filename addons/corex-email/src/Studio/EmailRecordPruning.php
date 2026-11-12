<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * Removes Email Studio records of a kind that were made before a date: captured and held emails
 * whose 30 days have passed (spec 113, FR-020).
 */
interface EmailRecordPruning
{
    /**
     * Remove at most `$limit` records of `$type` made before `$cutoff`, oldest first.
     *
     * @return int How many were removed: fewer than `$limit` means none of that age is left.
     */
    public function pruneOlderThan(string $type, \DateTimeImmutable $cutoff, int $limit): int;
}
