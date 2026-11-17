<?php

/**
 * Plugin Name: CoreX E2E logo set (test fixture)
 * Description: Test fixture. Shows one request the login in a chosen CoreX appearance, with a site logo, with a site icon, or under another theme.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * The browser checks of the logo set (spec 134, US-BRD-05: `tests/e2e/logo-set.spec.js`).
 *
 * The admin appearance, the Branding logo, the site icon and the active theme are each a choice of
 * the whole site, and the other spec files of a run read the admin and the front end at the same
 * time, so none is saved. A request that carries one of these cookies sees it for that request only:
 *
 * - `corex_e2e_appearance`: `dark`, `light` or `system`, read as the saved CoreX admin appearance;
 * - `corex_e2e_logo_url`: a path on this site, read as the logo set in CoreX Settings, Branding, or
 *   `none` for no logo there;
 * - `corex_e2e_site_icon`: a path on this site, answered as the site icon WordPress links, or `none`
 *   for no site icon;
 * - `corex_e2e_theme=other`: the first installed theme that is neither the CoreX theme nor built on
 *   it, named in the answer's `X-Corex-E2E-Theme` header (none when there is no such theme).
 *
 * Copied into `wp-content/mu-plugins/` with the other `corex-e2e-*.php` fixtures, by the CI jobs
 * and by `scripts/setup-wordpress.ps1`.
 */

/**
 * A cookie's choice: the address of a path on this site, '' for `none`, or null when the cookie
 * names neither.
 */
function corex_e2e_logo_set_choice(string $cookie): ?string
{
    $value = isset($_COOKIE[$cookie]) ? (string) wp_unslash($_COOKIE[$cookie]) : '';

    if ($value === 'none') {
        return '';
    }

    return preg_match('#^/[A-Za-z0-9/_.\-]*$#', $value) === 1 ? home_url($value) : null;
}

$corexAppearance = (string) ($_COOKIE['corex_e2e_appearance'] ?? '');

if (in_array($corexAppearance, ['dark', 'light', 'system'], true)) {
    add_filter('pre_option_corex_brand_admin_appearance', static fn (): string => $corexAppearance);
}

$corexLogo = corex_e2e_logo_set_choice('corex_e2e_logo_url');

if ($corexLogo !== null) {
    add_filter('pre_option_corex_brand_logo_url', static fn (): string => $corexLogo);
}

$corexIcon = corex_e2e_logo_set_choice('corex_e2e_site_icon');

if ($corexIcon !== null) {
    add_filter('get_site_icon_url', static fn (): string => $corexIcon);
}

if (($_COOKIE['corex_e2e_theme'] ?? '') === 'other') {
    // Before WordPress loads the theme's functions.php, which follows `setup_theme`.
    add_action('setup_theme', static function (): void {
        $other = '';

        foreach (wp_get_themes() as $stylesheet => $theme) {
            if ($stylesheet !== 'corex' && $theme->get_template() !== 'corex' && $theme->errors() === false) {
                $other = (string) $stylesheet;
                break;
            }
        }

        if ($other === '') {
            return;
        }

        $template = (string) wp_get_theme($other)->get_template();
        add_filter('pre_option_stylesheet', static fn (): string => $other);
        add_filter('pre_option_template', static fn (): string => $template);
        add_action('send_headers', static function () use ($other): void {
            header('X-Corex-E2E-Theme: ' . $other);
        });
    }, 0);
}
