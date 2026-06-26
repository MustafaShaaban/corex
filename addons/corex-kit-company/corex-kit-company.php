<?php
/**
 * Plugin Name:       Corex Kit — Company Website
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Company Website starter kit — a Blueprint that composes the Corex UI blocks/patterns with the theme's universal FSE templates into a neutral, accessible, RTL company site. Composes modules; no business logic.
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
 * @package Corex\Kit
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_KIT_COMPANY_VERSION')) {
    define('COREX_KIT_COMPANY_VERSION', '0.31.0');
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

// Behavior is contributed by Corex\Kit\KitServiceProvider via corex-core's Boot list.
