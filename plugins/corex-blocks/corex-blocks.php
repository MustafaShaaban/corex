<?php
/**
 * Plugin Name:       Corex Blocks
 * Plugin URI:        https://github.com/bseit/corex
 * Description:       The Corex block engine — auto-discovered FSE blocks, model→block connectors, and conditional (per-block) asset loading.
 * Version:           0.30.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Blocks
 */

declare(strict_types=1);

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_BLOCKS_VERSION')) {
    define('COREX_BLOCKS_VERSION', '0.30.0');
}
if (! defined('COREX_BLOCKS_FILE')) {
    define('COREX_BLOCKS_FILE', __FILE__);
}
if (! defined('COREX_BLOCKS_PATH')) {
    define('COREX_BLOCKS_PATH', plugin_dir_path(__FILE__));
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

// No business logic yet. Block auto-discovery (build/*/block.json) and connector
// registration arrive in the corex-blocks module.
