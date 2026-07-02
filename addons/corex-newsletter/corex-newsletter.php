<?php
/**
 * Plugin Name:       Corex Newsletter
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Corex newsletter — topic-based subscriptions with double opt-in, secure unsubscribe/suppression, GDPR consent, and an on-publish trigger that emails confirmed subscribers. Built on Custom Tables, Mail, Captcha, and the event seam.
 * Version:           0.32.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 *
 * @package Corex\Newsletter
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_NEWSLETTER_VERSION')) {
    define('COREX_NEWSLETTER_VERSION', '0.32.0');
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

// Behavior is contributed by Corex\Newsletter\NewsletterServiceProvider via corex-core's Boot list.
