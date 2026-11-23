<?php

/**
 * Plugin Name: CoreX E2E child theme (test fixture)
 * Description: Test fixture. Shows one request under a child of the CoreX theme kept in tests/e2e/fixtures/themes.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * A child of the CoreX theme for one request (spec 137, FR-005: `tests/e2e/navigation-width.spec.js`).
 *
 * The active theme is a choice of the whole site, and the other spec files of a run read the front
 * end at the same time, so it is never saved. A request with the cookie `corex_e2e_child` set to the
 * folder name of a theme in `tests/e2e/fixtures/themes` (`corex-e2e-nav-child`) is drawn under that
 * theme, for that request only, and the answer names it in its `X-Corex-E2E-Child` header. A name
 * that is not such a folder, or a folder whose theme is not a child of the CoreX theme, changes
 * nothing.
 *
 * The folder is found from the CoreX theme, which every install the suite runs on links from the
 * repository (`.github/actions/provision-wordpress`, `scripts/setup-wordpress.ps1`), so nothing is
 * copied into `wp-content/themes`. The child's own files are read from there; its address is not
 * served, and a child made for a test asks for none.
 *
 * Copied into `wp-content/mu-plugins/` with the other `corex-e2e-*.php` fixtures, by the CI jobs
 * and by `scripts/setup-wordpress.ps1`.
 */
$corexChild = (string) ($_COOKIE['corex_e2e_child'] ?? '');
$corexTheme = realpath(WP_CONTENT_DIR . '/themes/corex');

if (preg_match('/^corex-e2e-[a-z0-9-]+$/D', $corexChild) !== 1 || $corexTheme === false) {
    return;
}

$corexChildRoot = wp_normalize_path(dirname($corexTheme) . '/tests/e2e/fixtures/themes');

if (! is_file($corexChildRoot . '/' . $corexChild . '/style.css')) {
    return;
}

register_theme_directory($corexChildRoot);

// Before WordPress loads the theme's functions.php, which follows `setup_theme`.
add_action('setup_theme', static function () use ($corexChild, $corexChildRoot): void {
    if (wp_get_theme($corexChild, $corexChildRoot)->get_template() !== 'corex') {
        return;
    }

    add_filter('pre_option_stylesheet', static fn (): string => $corexChild);
    add_filter('pre_option_template', static fn (): string => 'corex');
    // WordPress looks for the active theme in the root it saved for it, which is the CoreX theme's.
    add_filter('pre_option_stylesheet_root', static fn (): string => $corexChildRoot);
    add_action('send_headers', static function () use ($corexChild): void {
        header('X-Corex-E2E-Child: ' . $corexChild);
    });
}, 0);
