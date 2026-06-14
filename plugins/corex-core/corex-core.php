<?php
/**
 * Plugin Name:       Corex Core
 * Plugin URI:        https://github.com/bseit/corex
 * Description:       The Corex MVC engine — self-booting core (Boot, PSR-11 container, controllers, services, repositories, events, abilities). Presentation-free.
 * Version:           0.26.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Update URI:        https://github.com/bseit/corex
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex
 */

declare(strict_types=1);

// Block direct access.
if (! defined('ABSPATH')) {
    exit;
}

// Base constants (idempotent).
if (! defined('COREX_CORE_VERSION')) {
    define('COREX_CORE_VERSION', '0.26.0');
}
if (! defined('COREX_CORE_FILE')) {
    define('COREX_CORE_FILE', __FILE__);
}
if (! defined('COREX_CORE_PATH')) {
    define('COREX_CORE_PATH', plugin_dir_path(__FILE__));
}

/*
 * Composer autoloader resolution.
 *
 * The authoritative autoloader is generated at the monorepo root. A plugin-local
 * vendor/ is supported for standalone installs. Loading MUST NOT fatal when no
 * autoloader is present yet (e.g. before `composer install`) — the plugin simply
 * stays dormant until the foundation module (PHASE 5) wires Boot on plugins_loaded.
 */
(static function (): void {
    $candidates = [
        __DIR__ . '/vendor/autoload.php',             // standalone install
        dirname(__DIR__, 2) . '/vendor/autoload.php',  // monorepo root
    ];
    foreach ($candidates as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            return;
        }
    }
})();

// Self-register the framework on plugins_loaded (no-op until the autoloader is present).
if (class_exists(\Corex\Boot::class)) {
    \Corex\Boot::init();
}
