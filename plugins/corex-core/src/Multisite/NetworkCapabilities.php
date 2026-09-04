<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Makes network-level authorization explicit and injectable (spec FR-042, FR-043).
 */
interface NetworkCapabilities
{
    public function currentUserIsSuperAdmin(): bool;

    public function currentUserCan(string $capability): bool;
}
