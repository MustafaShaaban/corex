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
 *   - `fail`: a production site that delivers, whose mail server takes nothing. WordPress is
 *     answered for before it reaches its mailer (`pre_wp_mail`), so nothing is sent: the
 *     email is tried and fails, which is the only way a browser can meet a reply that did
 *     not go.
 *
 * A request that also carries `X-Corex-E2E-Mail-Logo` is answered as a site with a logo, which
 * the suite's own site has not: `mark` for a square one and `name` for one that is the name
 * written out. Both are images WordPress itself ships, at the size they really are.
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
        'fail' => ['corex_app_env' => 'production', 'corex_mail_provider' => 'wp-mail', 'corex_mail_live_delivery' => '1'],
    ][$mode] ?? [];
    if ($mode === 'fail') {
        // Only for the person the settings above are answered for: for anybody else they are
        // the site's own, and so is its mail. Asked when an email is sent, by which time
        // WordPress knows who is signed in.
        add_filter(
            'pre_wp_mail',
            static fn (mixed $answered): mixed => current_user_can('manage_options') ? false : $answered,
            PHP_INT_MAX,
        );
    }

    foreach ($settings as $option => $value) {
        add_filter(
            'pre_option_' . $option,
            static fn (mixed $stored): mixed => current_user_can('manage_options') ? $value : $stored,
        );
    }
}, 1);

add_filter('corex_email_brand', static function (mixed $brand): mixed {
    $asked = isset($_SERVER['HTTP_X_COREX_E2E_MAIL_LOGO'])
        ? sanitize_key(wp_unslash((string) $_SERVER['HTTP_X_COREX_E2E_MAIL_LOGO']))
        : '';
    $image = ['mark' => 'w-logo-blue.png', 'name' => 'wordpress-logo.png'][$asked] ?? '';
    if ($image === '' || ! $brand instanceof \Corex\Email\Design\EmailBrand || ! current_user_can('manage_options')) {
        return $brand;
    }
    $size = getimagesize(ABSPATH . 'wp-admin/images/' . $image);

    return new \Corex\Email\Design\EmailBrand(
        $brand->name,
        $brand->homeUrl,
        admin_url('images/' . $image),
        $brand->direction,
        $brand->language,
        $brand->footer,
        $brand->light,
        $brand->dark,
        is_array($size) ? (int) $size[0] : 0,
        is_array($size) ? (int) $size[1] : 0,
    );
});

