<?php
/**
 * Plugin Name:       Corex Mail
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Corex transactional email engine — code-defined templates with safe merge variables, a secured send pipeline (header-injection guard + recipient validation), a driver abstraction, and an audit log. Built on the corex-core event seam + data layer.
 * Version:           0.25.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Email
 */

declare(strict_types=1);

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_EMAIL_VERSION')) {
    define('COREX_EMAIL_VERSION', '0.25.0');
}
if (! defined('COREX_EMAIL_PATH')) {
    define('COREX_EMAIL_PATH', plugin_dir_path(__FILE__));
}

/*
 * Autoloader resolution (shared root autoloader, with standalone fallback).
 * Must never fatal when absent.
 */
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

// The mail behavior is contributed by Corex\Email\MailServiceProvider, registered
// in corex-core's Boot provider list — it self-initializes on the standard boot pass.
