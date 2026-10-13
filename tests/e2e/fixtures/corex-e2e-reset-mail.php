<?php

/**
 * Plugin Name: CoreX E2E reset mail
 * Description: Lets the browser suite read the email a password reset would have sent, and sends nothing.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Keeps an email, for a request that asks for it (`account-reset.spec.js`).
 *
 * The account block asks WordPress for a reset email, and a browser cannot open a mailbox.
 * A request carrying the header `X-Corex-E2E-Reset-Mail` has every email it would send kept
 * in a transient named after the header's value, and WordPress is answered for before it
 * reaches its mailer (`pre_wp_mail`): nothing leaves. The spec reads the transient through
 * WP-CLI, which only somebody on the server can do.
 *
 * Without the header this file does nothing. With it, all a request gains is that its own
 * email is not sent.
 *
 * An mu-plugin so it is active with no activation step. How the fixtures reach an install is in
 * tests/e2e/playwright.config.js.
 */
add_filter('pre_wp_mail', static function (mixed $answered, mixed $email): mixed {
    $asked = isset($_SERVER['HTTP_X_COREX_E2E_RESET_MAIL'])
        ? sanitize_key(wp_unslash((string) $_SERVER['HTTP_X_COREX_E2E_RESET_MAIL']))
        : '';
    if ($asked === '' || ! is_array($email)) {
        return $answered;
    }

    set_transient('corex_e2e_reset_mail_' . $asked, (string) ($email['message'] ?? ''), 10 * MINUTE_IN_SECONDS);

    return true;
}, 10, 2);
