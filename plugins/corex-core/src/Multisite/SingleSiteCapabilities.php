<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Maps network authority to the established site-administrator capability off multisite.
 */
final class SingleSiteCapabilities implements NetworkCapabilities
{
    public function currentUserIsSuperAdmin(): bool
    {
        return $this->currentUserCan('manage_options');
    }

    public function currentUserCan(string $capability): bool
    {
        return current_user_can($capability);
    }
}
