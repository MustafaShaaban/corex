<?php
/**
 * Plugin Name:       Corex UI
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Corex UI block library — server-rendered corex/* dynamic blocks and a curated set of token-only, accessible, RTL section patterns under a "Corex" category. Built on the corex-blocks engine; designs compose these building units.
 * Version:           0.24.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Ui
 */

declare(strict_types=1);

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_UI_VERSION')) {
    define('COREX_UI_VERSION', '0.24.0');
}
if (! defined('COREX_UI_PATH')) {
    define('COREX_UI_PATH', plugin_dir_path(__FILE__));
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

// The UI behavior is contributed by Corex\Ui\UiServiceProvider, registered in
// corex-core's Boot provider list — it self-initializes on the standard boot pass.
