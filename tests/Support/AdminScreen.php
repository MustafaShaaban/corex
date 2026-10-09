<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

/**
 * Puts a test on a WordPress admin screen, or takes it off one.
 *
 * `set_current_screen()` and the `WP_Screen` it builds live under `wp-admin/includes/`, which
 * WordPress reads for an admin page and for nothing else. The integration suite loads WordPress
 * as a front-end request does, so a test that calls it has to load both files first. Seven test
 * files called it without, and passed only when an earlier test had loaded the admin for a reason
 * of its own: run alone, each failed with "Call to undefined function set_current_screen()".
 */
final class AdminScreen
{
    /**
     * @param string $id A screen id such as `dashboard`; `front` is what a front-end request has.
     */
    public static function set(string $id): void
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';

        set_current_screen($id);
    }
}
