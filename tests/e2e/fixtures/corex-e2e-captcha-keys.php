<?php

/**
 * Plugin Name: CoreX E2E captcha keys
 * Description: Lets the browser suite open Settings as a site with reCAPTCHA keys saved, without saving any.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Answers the captcha settings for one request (`truthful-screens.spec.js`, spec 114 US-SAFE-16).
 *
 * The "Test verification" button is offered only once a provider and both of its keys are saved.
 * The suite's site has none, and saving keys there would put a captcha in front of every form
 * that another spec submits while this one runs. A request carrying the header
 * `X-Corex-E2E-Captcha: keys` is answered as a site with reCAPTCHA chosen and two placeholder
 * keys, and `provider` as one with reCAPTCHA chosen and no keys. Nothing is sent to the provider
 * by this file, and nothing is stored: each setting is answered through its `pre_option_` filter. Without the header this file does nothing, and it
 * does nothing for a request that is not signed in as somebody who manages the site.
 *
 * An mu-plugin so it is active with no activation step. How the fixtures reach an install is in
 * `corex-e2e-core-notices.php`.
 */
add_action('plugins_loaded', static function (): void {
    $mode = isset($_SERVER['HTTP_X_COREX_E2E_CAPTCHA'])
        ? sanitize_key(wp_unslash((string) $_SERVER['HTTP_X_COREX_E2E_CAPTCHA']))
        : '';

    $settings = [
        'keys'     => [
            'corex_captcha_driver'   => 'recaptcha',
            'corex_captcha_site_key' => 'e2e-site-key',
            'corex_captcha_secret'   => 'e2e-secret-key',
        ],
        'provider' => [
            'corex_captcha_driver'   => 'recaptcha',
            'corex_captcha_site_key' => '',
            'corex_captcha_secret'   => '',
        ],
    ][$mode] ?? [];

    foreach ($settings as $option => $value) {
        add_filter(
            'pre_option_' . $option,
            static fn (mixed $stored): mixed => current_user_can('manage_options') ? $value : $stored,
        );
    }
}, 1);
