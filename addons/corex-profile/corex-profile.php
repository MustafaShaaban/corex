<?php
/**
 * Plugin Name:       Corex Profile
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       Front-office accounts for Corex — login, registration, forgot/reset password, profile, notifications, and active-session management, layered over WordPress authentication. Keeps the front office separate from wp-admin and preserves lockout-safe recovery. Presentation lives in the theme; this add-on owns the account logic.
 * Version:           0.33.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 *
 * @package Corex\Profile
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_PROFILE_VERSION')) {
    define('COREX_PROFILE_VERSION', '0.33.0');
}

(static function (): void {
    $candidates = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__, 2) . '/vendor/autoload.php',
    ];
    foreach ($candidates as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            return;
        }
    }
})();

// Behavior is contributed by Corex\Profile\ProfileServiceProvider via corex-core's Boot list.
