<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\SiteAdministration;

/**
 * A role or a person administers the site when WordPress gives it `manage_options` (spec 117,
 * ACC-D11). WordPress answers yes for a super administrator on a network.
 */
final class WpSiteAdministration implements SiteAdministration
{
    public function roleAdministersSite(string $roleKey): bool
    {
        $role = get_role($roleKey);

        return $role !== null && $role->has_cap('manage_options');
    }

    public function userAdministersSite(int $userId): bool
    {
        return $userId > 0 && user_can($userId, 'manage_options');
    }
}
