<?php

/**
 * The machine boundary holds (spec 083, FR-002 / SC-004; spec 079's unwritten T032).
 *
 * `wp_die()` chooses its handler by request type *before* any filter runs
 * (`wp-includes/functions.php:3791-3849`): Ajax, JSON/REST, JSONP, XML-RPC and XML/feeds each reach
 * their own filter, and only the final `else` reaches `wp_die_handler`. CoreX filters that one and
 * no other, which is why a branded error page cannot be returned to a script.
 *
 * That is an argument about core's control flow, and arguments are what this file exists to stop
 * being trusted. It asserts the actual subscriber list on this install.
 *
 * @package Corex\Tests\Integration\Admin
 */

declare(strict_types=1);

use Corex\Admin\Errors\AdminDieHandler;
use Corex\Boot;

/**
 * The filters WordPress consults for every non-HTML caller. CoreX must appear on none of them.
 */
const MACHINE_DIE_FILTERS = [
    'wp_die_ajax_handler',
    'wp_die_json_handler',
    'wp_die_jsonp_handler',
    'wp_die_xmlrpc_handler',
    'wp_die_xml_handler',
];

it('subscribes to no machine wp_die handler', function () {
    Boot::app()->container()->make(AdminDieHandler::class)->register();

    foreach (MACHINE_DIE_FILTERS as $filter) {
        expect(has_filter($filter))->toBeFalse(
            "{$filter} must have no CoreX subscriber: it is how a script is answered."
        );
    }
});

it('does answer the html handler, which is the one a person reaches', function () {
    Boot::app()->container()->make(AdminDieHandler::class)->register();

    expect(has_filter('wp_die_handler'))->not->toBeFalse();
});

/**
 * The `is_admin()` requirement, checked from the front end.
 *
 * Registering the filter is not the same as taking the request: the handler decides per call, and
 * a front-end `wp_die()` — a theme's own error, a plugin's — must still be WordPress's.
 */
it('hands a front-end wp_die back to WordPress', function () {
    $handler = Boot::app()->container()->make(AdminDieHandler::class);

    $wasAdmin = $GLOBALS['current_screen'] ?? null;
    unset($GLOBALS['current_screen']);
    set_current_screen('front');

    expect($handler->handlerFor('_default_wp_die_handler'))->toBe('_default_wp_die_handler');

    if ($wasAdmin !== null) {
        $GLOBALS['current_screen'] = $wasAdmin;
    }
});

it('takes an admin wp_die', function () {
    $handler = Boot::app()->container()->make(AdminDieHandler::class);

    set_current_screen('dashboard');

    expect($handler->handlerFor('_default_wp_die_handler'))->not->toBe('_default_wp_die_handler');
});
