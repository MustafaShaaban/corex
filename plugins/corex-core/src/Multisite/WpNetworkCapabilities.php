<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

final class WpNetworkCapabilities implements NetworkCapabilities
{
    public function currentUserIsSuperAdmin(): bool
    {
        return is_super_admin();
    }

    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }
}
