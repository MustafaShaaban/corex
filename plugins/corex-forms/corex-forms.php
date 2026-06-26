<?php
/**
 * Plugin Name:       Corex Forms
 * Plugin URI:        https://github.com/bseit/corex
 * Description:       The Corex forms engine — code-defined form schemas, a headless validator, a secured submit lifecycle, and an FSE form block. Built on the corex-core event seam + security middleware.
 * Version:           0.31.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Forms
 */

declare(strict_types=1);

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_FORMS_VERSION')) {
    define('COREX_FORMS_VERSION', '0.31.0');
}
if (! defined('COREX_FORMS_FILE')) {
    define('COREX_FORMS_FILE', __FILE__);
}
if (! defined('COREX_FORMS_PATH')) {
    define('COREX_FORMS_PATH', plugin_dir_path(__FILE__));
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

// The forms behavior is contributed by Corex\Forms\FormsServiceProvider, registered
// in corex-core's Boot provider list — it self-initializes on the standard boot pass.
