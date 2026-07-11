<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

interface LoginLockoutStore
{
    public function releaseActiveLockouts(DateTimeImmutable $now): int;
}
