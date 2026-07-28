<?php
/**
 * Plugin Name:       Corex Guides
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       In-admin user guides, and the registry any site built on Corex extends to add its own. Ships Corex's guides for the admin dashboard; a client plugin registers guides for its own post types and flows through the same public API.
 * Version:           0.39.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Guides
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_GUIDES_VERSION')) {
    define('COREX_GUIDES_VERSION', '0.39.0');
}

if (! defined('COREX_GUIDES_FILE')) {
    define('COREX_GUIDES_FILE', __FILE__);
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

// Behavior is contributed by Corex\Guides\GuidesServiceProvider via corex-core's Boot list.
