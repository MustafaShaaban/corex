<?php
/**
 * Plugin Name:       CoreX Assistant
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       A chat on every public page that answers visitors from the site's own content, through free model tiers chained with fallback, and hands a visitor to the team through a CoreX form when it cannot help. Off until switched on and keyed in Settings.
 * Version:           0.48.1
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 *
 * @package Corex\Assistant
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_ASSISTANT_VERSION')) {
    define('COREX_ASSISTANT_VERSION', '0.48.1');
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

// Behavior is contributed by Corex\Assistant\AssistantServiceProvider via corex-core's Boot list.
