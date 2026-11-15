<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

interface LoginAttemptStore
{
    public function record(LoginAttemptRecord $record): void;

    /**
     * Failures since a moment, stored under any of the attempt's pairs.
     *
     * @return list<LoginAttemptRecord>
     */
    public function failures(LoginAttemptKeys $keys, DateTimeImmutable $since): array;

    /** The lockout that ends last among those still active, stored under any of the attempt's pairs. */
    public function latestLockout(LoginAttemptKeys $keys, DateTimeImmutable $now): ?LoginAttemptRecord;
}
