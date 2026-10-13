<?php

/**
 * Plugin Name: CoreX E2E REST address
 * Description: Lets the browser suite ask for a page as a site without pretty permalinks spells its REST addresses, whatever the install is set to.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Decides, for one request, how WordPress spells a REST address (`rest-address-plain.spec.js`).
 *
 * With pretty permalinks a route is a path, `/wp-json/corex/v1/submissions`. Without them it is
 * a query, `/index.php?rest_route=/corex/v1/submissions`, and a client that adds its own query
 * with a second `?` asks for a route WordPress does not know. CI's site has pretty permalinks,
 * as a real site has, so the Submissions inbox failed to open on a site without them and no
 * browser test could see it.
 *
 * A request carrying the header `X-Corex-E2E-Rest-Address: plain` is given every `rest_url()`
 * in the second spelling, written the way `get_rest_url()` writes it for a site with no
 * permalink structure. Only the address a page is handed changes: WordPress answers a
 * `rest_route` address on every site, whatever its permalinks, so the requests the page then
 * makes are answered by the same routes.
 *
 * The permalink setting itself is left alone, on purpose. Answering it as empty for one request
 * would also change what that request does with the rewrite rules, and a page that saves them
 * would save a site with none.
 *
 * Nothing is stored. Without the header this file does nothing, and it does nothing for a
 * request that is not signed in as somebody who manages the site.
 *
 * An mu-plugin so it is active with no activation step. How the fixtures reach an install is in
 * `corex-e2e-core-notices.php`.
 */
add_filter('rest_url', static function (mixed $url, mixed $path): mixed {
    $asked = isset($_SERVER['HTTP_X_COREX_E2E_REST_ADDRESS'])
        ? sanitize_key(wp_unslash((string) $_SERVER['HTTP_X_COREX_E2E_REST_ADDRESS']))
        : '';
    if ($asked !== 'plain' || ! is_string($url) || ! is_string($path)) {
        return $url;
    }
    // Asked when a page is being built, by which time WordPress knows who is signed in. An
    // address asked for before that is left as the site spells it.
    if (! function_exists('wp_get_current_user') || ! current_user_can('manage_options')) {
        return $url;
    }

    return add_query_arg(
        'rest_route',
        '/' . ltrim($path, '/'),
        trailingslashit(home_url()) . 'index.php',
    );
}, 10, 2);
