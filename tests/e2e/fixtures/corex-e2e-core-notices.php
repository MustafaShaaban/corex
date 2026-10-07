<?php

/**
 * Plugin Name: CoreX E2E core notices
 * Description: Lets the browser suite ask for an admin page with a core update pending, or with none, whatever version of WordPress the install runs.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Decides, for one request, what WordPress prints before the page (`admin-core-notices.spec.js`).
 *
 * Core's update nag is printed only while an update is pending. CI installs the latest WordPress,
 * where none is, so a defect in where the nag is drawn was visible on a development install and
 * on client sites, and never in CI.
 *
 * `?corex_e2e_core_notices=pending` answers the `update_core` transient for that request with one
 * pending release, so core's own `update_nag()` prints its own markup, and adds one notice on
 * `admin_notices` and a dismissible one on `all_admin_notices`. `=none` answers it with no
 * pending release, so no nag is printed on an install that is behind either.
 *
 * Nothing is stored: the transient is answered through its `pre_` filter and the database row is
 * neither read nor written. Without the query argument this file does nothing, and it does
 * nothing for somebody who may not update core.
 *
 * An mu-plugin so it is active with no activation step.
 *
 * Every `tests/e2e/fixtures/corex-e2e-*.php` is copied into `wp/wp-content/mu-plugins/` by the
 * browser job in `.github/workflows/ci.yml`, and on a development install by
 * `scripts/setup-wordpress.ps1`. The script copies into the single-site install only, and not
 * once a client site is linked there: it prints the command instead. A copy does not follow an
 * edit, so run the script again after changing this file.
 */
add_action('admin_init', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only switch for one page load; it changes nothing that is stored.
    $mode = isset($_GET['corex_e2e_core_notices']) ? sanitize_key(wp_unslash($_GET['corex_e2e_core_notices'])) : '';

    if (! in_array($mode, ['pending', 'none'], true) || ! current_user_can('update_core')) {
        return;
    }

    add_filter('pre_site_transient_update_core', static fn (): object => (object) [
        'updates' => $mode === 'pending'
            ? [(object) ['response' => 'upgrade', 'current' => '99.0', 'version' => '99.0', 'locale' => get_locale()]]
            : [],
        // Both, and current: without them WordPress takes the answer for a stale check and asks
        // api.wordpress.org again on this request.
        'version_checked' => wp_get_wp_version(),
        'last_checked' => time(),
    ]);

    if ($mode !== 'pending') {
        return;
    }

    add_action('admin_notices', static function (): void {
        wp_admin_notice('A notice printed on admin_notices.', [
            'type' => 'info',
            'id' => 'corex-e2e-notice-info',
        ]);
    });

    add_action('all_admin_notices', static function (): void {
        wp_admin_notice('A dismissible notice printed on all_admin_notices.', [
            'type' => 'error',
            'id' => 'corex-e2e-notice-error',
            'dismissible' => true,
        ]);
    });
});
