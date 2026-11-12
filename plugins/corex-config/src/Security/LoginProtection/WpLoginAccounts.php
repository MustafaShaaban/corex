<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use WP_User;

/**
 * WordPress's users, read the way WordPress signs a person in: the login first, and the email for
 * an address typed as the username.
 */
final class WpLoginAccounts implements LoginAccounts
{
    public function idFor(string $typed): ?int
    {
        $typed = trim($typed);
        if ($typed === '') {
            return null;
        }

        $user = get_user_by('login', $typed);
        if (! $user instanceof WP_User && is_email($typed) !== false) {
            $user = get_user_by('email', $typed);
        }

        return $user instanceof WP_User ? (int) $user->ID : null;
    }

    public function nameOf(int $userId): string
    {
        $user = get_userdata($userId);
        if (! $user instanceof WP_User) {
            return sprintf(
                /* translators: %d: WordPress user ID. */
                __('User %d', 'corex'),
                $userId,
            );
        }

        return $user->display_name !== '' ? $user->display_name : $user->user_login;
    }
}
