<?php
/**
 * Plugin Name:       Corex Kit — Portfolio
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Portfolio starter kit — a projects CPT, a dynamic projects-grid block, portfolio FSE templates, and a Blueprint. Composes the Corex blocks engine; presentation + a portfolio domain.
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
 * @package Corex\Portfolio
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_KIT_PORTFOLIO_VERSION')) {
    define('COREX_KIT_PORTFOLIO_VERSION', '0.31.0');
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

// Behavior is contributed by Corex\Portfolio\PortfolioServiceProvider via corex-core's Boot list.
