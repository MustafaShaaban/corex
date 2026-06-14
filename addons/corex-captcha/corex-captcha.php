<?php
/**
 * Plugin Name:       Corex Captcha
 * Plugin URI:        https://github.com/MustafaShaaban/corex
 * Description:       The Corex captcha driver system — verify an anti-bot challenge behind one interface (none / honeypot / reCAPTCHA / Turnstile / hCaptcha), selected by configuration. Fail-closed for remote providers.
 * Version:           0.25.0
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Requires Plugins:  corex-core
 * Author:            Mustafa Shaaban
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       corex
 *
 * @package Corex\Captcha
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('COREX_CAPTCHA_VERSION')) {
    define('COREX_CAPTCHA_VERSION', '0.25.0');
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

// Behavior is contributed by Corex\Captcha\CaptchaServiceProvider via corex-core's Boot list.
