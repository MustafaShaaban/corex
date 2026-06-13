<?php
/**
 * Plugin Name:       Corex Config
 * Plugin URI:        https://github.com/bseit/corex
 * Description:       Corex settings + environment layer — admin settings UI, .env resolution, feature flags, GTM, and security headers. Business-logic-free.
 * Version:           0.24.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban + team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Config
 */

declare(strict_types=1);

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_CONFIG_VERSION')) {
    define('COREX_CONFIG_VERSION', '0.24.0');
}
if (! defined('COREX_CONFIG_FILE')) {
    define('COREX_CONFIG_FILE', __FILE__);
}
if (! defined('COREX_CONFIG_PATH')) {
    define('COREX_CONFIG_PATH', plugin_dir_path(__FILE__));
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

// No business logic yet. The three-tier Config resolver (.env → options → defaults)
// and the Config facade arrive in the corex-core foundation module.
