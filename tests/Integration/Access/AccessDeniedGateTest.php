<?php

/**
 * Telling a refusal apart from a missing page (spec 079, T042).
 *
 * WordPress fires `admin_page_access_denied` for both causes. Before this spec the gate assumed the
 * first, so an administrator opening `?page=corex-nonexistent` was told at HTTP 403 that their role
 * lacked `manage_options` — and offered a form to request access to a screen that does not exist.
 *
 * The four cases below are the smallest honest matrix. The first implementation of this check read
 * only `$_registered_pages` and passed three of them: it reported every real CoreX screen as
 * missing to exactly the people the gate exists for, because `add_submenu_page()` returns before
 * touching that global when the viewer lacks the capability.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Access\AccessDeniedGate;

/**
 * The gate's verdict for a slug, read without letting it end the request.
 *
 * `intercept()` exits on both branches, so the decision is taken from the private predicate. It is
 * the branch point and the only thing that was wrong; what each branch then renders is asserted in
 * the browser spec.
 */
function gateSeesDenial(string $page): bool
{
    $gate   = Boot::app()->container()->make(AccessDeniedGate::class);
    $method = new ReflectionMethod($gate, 'isDenial');

    return (bool) $method->invoke($gate, $page);
}

beforeEach(function () {
    $this->menuNopriv    = $GLOBALS['_wp_menu_nopriv'] ?? [];
    $this->submenuNopriv = $GLOBALS['_wp_submenu_nopriv'] ?? [];
    $this->registered    = $GLOBALS['_registered_pages'] ?? [];
});

afterEach(function () {
    $GLOBALS['_wp_menu_nopriv']    = $this->menuNopriv;
    $GLOBALS['_wp_submenu_nopriv'] = $this->submenuNopriv;
    $GLOBALS['_registered_pages']  = $this->registered;
});

it('calls a real screen the viewer may not open a denial', function () {
    // What `add_submenu_page()` records for a subscriber: the page exists, they may not have it.
    $GLOBALS['_wp_submenu_nopriv'] = ['corex-settings' => ['corex-forms' => true]];
    $GLOBALS['_registered_pages']  = [];

    expect(gateSeesDenial('corex-forms'))->toBeTrue();
});

it('calls a real screen the viewer may open a denial too, because something else refused it', function () {
    // For an administrator the page is in `$_registered_pages` instead, and a refusal at that point
    // is a real refusal — not this gate's business to second-guess.
    $GLOBALS['_wp_submenu_nopriv'] = [];
    $GLOBALS['_registered_pages']  = ['corex-settings_page_corex-forms' => true];

    expect(gateSeesDenial('corex-forms'))->toBeTrue();
});

it('does not call a page nobody registered a denial, whoever is asking', function () {
    $GLOBALS['_wp_menu_nopriv']    = [];
    $GLOBALS['_wp_submenu_nopriv'] = ['corex-settings' => ['corex-forms' => true]];
    $GLOBALS['_registered_pages']  = ['corex-settings_page_corex-forms' => true];

    expect(gateSeesDenial('corex-nonexistent'))->toBeFalse();
});

it('calls a top-level page the viewer may not open a denial', function () {
    $GLOBALS['_wp_menu_nopriv']    = ['corex-settings' => true];
    $GLOBALS['_wp_submenu_nopriv'] = [];
    $GLOBALS['_registered_pages']  = [];

    expect(gateSeesDenial('corex-settings'))->toBeTrue();
});
