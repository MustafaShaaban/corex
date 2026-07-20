<?php
/**
 * Plugin Name:       Corex Kit — WooCommerce
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The WooCommerce store starter kit — a Blueprint and shop composition, gated behind the woocommerce_kit feature flag and WooCommerce's presence. Never a hard dependency: it self-disables when Woo is inactive.
 * Version:           0.34.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 * Domain Path:       /languages
 *
 * @package Corex\Woo
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_KIT_WOO_VERSION')) {
    define('COREX_KIT_WOO_VERSION', '0.34.0');
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

// Declare HPOS (High-Performance Order Storage) compatibility. The kit is presentation
// + a blueprint and never reads orders by direct post meta, so it is HPOS-safe.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Behavior is contributed by Corex\Woo\WooServiceProvider via corex-core's Boot list,
// gated on WooCommerce being active AND the woocommerce_kit feature flag.
