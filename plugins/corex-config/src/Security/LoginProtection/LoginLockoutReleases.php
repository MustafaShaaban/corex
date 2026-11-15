<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Ends lockouts released from the screen (spec 124, FR-017).
 *
 * A lockout is named by its stored row, so no username or address is needed to find it. Each
 * release is one write that ends only the rows still active and not released already, so two
 * releases at once end each row once, and the second learns from its count that there was nothing
 * left to end.
 */
interface LoginLockoutReleases
{
    /** The lockout stored in this row, or null when the row is gone or is not a lockout. */
    public function lockoutById(int $id): ?LoginAttemptRecord;

    /**
     * Ends every lockout still active at `$now` stored under this identity and network pair, as
     * released by the owner, and returns how many rows it ended.
     */
    public function releasePair(string $identityHash, string $networkHash, DateTimeImmutable $now): int;

    /** Ends every lockout still active at `$now`, as released by the owner, and returns how many. */
    public function releaseAllActive(DateTimeImmutable $now): int;
}
