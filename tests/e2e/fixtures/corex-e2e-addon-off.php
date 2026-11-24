<?php

/**
 * Plugin Name: CoreX E2E add-on off (test fixture)
 * Description: Test fixture. Draws one request as if one CoreX add-on were not active.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * One CoreX add-on off for one request (spec 137, US-NAV-02 criterion 6: `tests/e2e/mega-menu.spec.js`).
 *
 * Turning an add-on off is a choice of the whole site, and the other spec files of a run read the
 * site at the same time, so it is never saved. A request with the cookie `corex_e2e_addon_off` set to
 * the folder name of a CoreX add-on (`corex-ui`) is drawn with that add-on's plug-in file left out of
 * the active plug-ins WordPress reads: WordPress does not load it, and CoreX, which reads the same
 * list, boots none of what it contributes. A name that is not a `corex-` folder changes nothing.
 *
 * Copied into `wp-content/mu-plugins/` with the other `corex-e2e-*.php` fixtures: must-use plug-ins
 * load before the plug-ins, so the list is filtered before it is read.
 */
$corexOff = isset($_COOKIE['corex_e2e_addon_off']) ? sanitize_key(wp_unslash((string) $_COOKIE['corex_e2e_addon_off'])) : '';

if (preg_match('/^corex-[a-z0-9-]+$/D', $corexOff) !== 1) {
    return;
}

add_filter('option_active_plugins', static function (mixed $plugins) use ($corexOff): mixed {
    if (! is_array($plugins)) {
        return $plugins;
    }

    return array_values(array_filter(
        $plugins,
        static fn (mixed $plugin): bool => ! is_string($plugin) || ! str_starts_with($plugin, $corexOff . '/'),
    ));
});

add_action('send_headers', static function () use ($corexOff): void {
    header('X-Corex-E2E-Addon-Off: ' . $corexOff);
});
