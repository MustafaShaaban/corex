<?php

/**
 * Plugin Name: CoreX E2E mail delivery
 * Description: Lets the browser suite ask for a request as a site that keeps its email, or as one with live delivery off, whatever the install is set to.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Decides, for one request, what CoreX does with an email (`submission-reply.spec.js`).
 *
 * What happens to a reply is decided from three settings: the environment, the delivery
 * provider and whether live delivery is on. CI's site is a production site with no provider, so
 * it refuses every email; a development install may deliver for real. Neither can show both
 * things the reply box has to say, and a test must never hand a message to a real mailbox.
 *
 * A request carrying the header `X-Corex-E2E-Mail` is answered as if those settings were:
 *
 *   - `capture`: a development site. An email is kept in Email Studio and reaches nobody.
 *   - `refuse`: a production site with a provider chosen and live delivery off.
 *
 * Nothing is stored: each setting is answered through its `pre_option_` filter and the database
 * row is neither read nor written. Without the header this file does nothing, and it does
 * nothing for a request that is not signed in as somebody who manages the site.
 *
 * An mu-plugin so it is active with no activation step. How the fixtures reach an install is in
 * `corex-e2e-core-notices.php`.
 */
add_action('plugins_loaded', static function (): void {
    $mode = isset($_SERVER['HTTP_X_COREX_E2E_MAIL'])
        ? sanitize_key(wp_unslash((string) $_SERVER['HTTP_X_COREX_E2E_MAIL']))
        : '';
    $settings = [
        'capture' => ['corex_app_env' => 'development'],
        'refuse' => ['corex_app_env' => 'production', 'corex_mail_provider' => 'wp-mail', 'corex_mail_live_delivery' => '0'],
    ][$mode] ?? [];

    foreach ($settings as $option => $value) {
        add_filter(
            'pre_option_' . $option,
            static fn (mixed $stored): mixed => current_user_can('manage_options') ? $value : $stored,
        );
    }
}, 1);
