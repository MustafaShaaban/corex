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
     * @return list<LoginAttemptRecord>
     */
    public function failures(string $identityHash, string $networkHash, DateTimeImmutable $since): array;

    public function latestLockout(string $identityHash, string $networkHash, DateTimeImmutable $now): ?LoginAttemptRecord;
}
