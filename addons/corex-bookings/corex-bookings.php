<?php
/**
 * Plugin Name:       Corex Bookings
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       Request a call with a company leader — a secured request form that stores the request and notifies the leader + confirms the visitor. Built on Custom Tables, Mail, and Captcha.
 * Version:           0.35.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 *
 * @package Corex\Bookings
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_BOOKINGS_VERSION')) {
    define('COREX_BOOKINGS_VERSION', '0.35.0');
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

// Behavior is contributed by Corex\Bookings\BookingsServiceProvider via corex-core's Boot list.
