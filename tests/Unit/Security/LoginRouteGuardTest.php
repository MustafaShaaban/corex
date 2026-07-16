<?php

/**
 * Unit tests for the custom login route / default endpoint guard.
 *
 * The rules used to live in two methods that disagreed — decision() let logged-in visitors reach
 * wp-login.php, hidesDefaultEndpoint() blocked them — and the old version of this file asserted
 * BOTH as correct. There is one rule set now.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;

function routePolicy(array $overrides = []): LoginProtectionSettings
{
    return new LoginProtectionSettings(
        enabled: $overrides['enabled'] ?? true,
        customSlug: $overrides['customSlug'] ?? 'team-login',
        blockDefaultEndpoints: $overrides['blockDefaultEndpoints'] ?? true,
        threshold: 5,
        windowSeconds: 300,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: true,
    );
}

beforeEach(function () {
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $key === 'permalink_structure' ? '/%postname%/' : $default);
});

it('hides the default login endpoint from anonymous visitors', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('hide')
        ->and($guard->entryPointFor('/wp-login', isAdmin: false))->toBe('hide')
        ->and($guard->entryPointFor('/wp-login/', isAdmin: false))->toBe('hide');
});

it('hides the default login endpoint from logged-in visitors too', function () {
    // Deliberate, and only safe because every login-bearing URL is rewritten to the slug:
    // core builds the post-password form from site_url('wp-login.php?action=postpass', ...) and
    // the auth-check iframe from wp_login_url(), both of which are filtered. Nothing legitimate
    // points at wp-login.php any more, so there is no reason to answer it for anyone.
    $guard = new LoginRouteGuard(routePolicy());

    // Login state is not an input here at all — that is the point.
    expect($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('hide');
});

it('serves the custom slug', function () {
    $guard = new LoginRouteGuard(routePolicy(['customSlug' => 'secure-entry']));

    expect($guard->entryPointFor('/secure-entry/', isAdmin: false))->toBe('serve_login')
        ->and($guard->entryPointFor('/secure-entry', isAdmin: false))->toBe('serve_login')
        ->and($guard->customLoginPath())->toBe('/secure-entry/')
        ->and($guard->movesCoreFiles())->toBeFalse();
});

it('leaves unrelated public routes alone', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->entryPointFor('/about/', isAdmin: false))->toBe('pass')
        ->and($guard->entryPointFor('/wp-content/uploads/logo.png', isAdmin: false))->toBe('pass')
        ->and($guard->entryPointFor('/', isAdmin: false))->toBe('pass');
});

it('never hides admin-context requests at the entry point', function () {
    // admin-ajax.php and admin-post.php are is_admin(); hiding them breaks scheduled work and
    // every async feature on the site.
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->entryPointFor('/wp-admin/admin-ajax.php', isAdmin: true))->toBe('pass')
        ->and($guard->entryPointFor('/wp-admin/admin-post.php', isAdmin: true))->toBe('pass');
});

it('serves the slug even when default-endpoint hiding is switched off', function () {
    $guard = new LoginRouteGuard(routePolicy(['blockDefaultEndpoints' => false]));

    expect($guard->entryPointFor('/team-login/', isAdmin: false))->toBe('serve_login')
        ->and($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('pass');
});

it('does nothing at all when protection is disabled', function () {
    $guard = new LoginRouteGuard(routePolicy(['enabled' => false]));

    expect($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('pass')
        ->and($guard->entryPointFor('/team-login/', isAdmin: false))->toBe('pass');
});

it('hides the admin area from anonymous visitors', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'index.php', path: '/wp-admin/'))
        ->toBeTrue();
});

it('never hides the admin area from a logged-in user, ajax, admin-post, or options.php', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->hidesAdminArea(isAdmin: true, loggedIn: true, ajax: false, script: 'index.php', path: '/wp-admin/'))->toBeFalse()
        ->and($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: true, script: 'admin-ajax.php', path: '/wp-admin/admin-ajax.php'))->toBeFalse()
        ->and($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'admin-ajax.php', path: '/wp-admin/admin-ajax.php'))->toBeFalse()
        ->and($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'admin-post.php', path: '/wp-admin/admin-post.php'))->toBeFalse()
        ->and($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'options.php', path: '/wp-admin/options.php'))->toBeFalse()
        ->and($guard->hidesAdminArea(isAdmin: false, loggedIn: false, ajax: false, script: 'index.php', path: '/about/'))->toBeFalse();
});

it('never hides the admin area when default-endpoint hiding is off', function () {
    $guard = new LoginRouteGuard(routePolicy(['blockDefaultEndpoints' => false]));

    expect($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'index.php', path: '/wp-admin/'))
        ->toBeFalse();
});

it('matches the slug by query string when the site uses plain permalinks', function () {
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $key === 'permalink_structure' ? '' : $default);
    $guard = new LoginRouteGuard(routePolicy(['customSlug' => 'team-login']));

    $_GET = ['team-login' => ''];
    expect($guard->entryPointFor('/', isAdmin: false))->toBe('serve_login');

    $_GET = [];
    expect($guard->entryPointFor('/', isAdmin: false))->toBe('pass');
});
