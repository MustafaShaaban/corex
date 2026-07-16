<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Reads recorded lockouts so the Security Center can show what actually happened.
 *
 * Separate from {@see LoginLockoutStore} on purpose: the recovery CLI command only ever releases
 * lockouts, and should not have to know how to list them.
 */
interface LoginLockoutReader
{
    /**
     * Most recent lockouts first, active ones included.
     *
     * @return list<LoginAttemptRecord>
     */
    public function recentLockouts(DateTimeImmutable $now, int $limit = 20): array;
}
