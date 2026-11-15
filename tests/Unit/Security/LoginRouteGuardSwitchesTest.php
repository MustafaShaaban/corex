<?php

/**
 * Each login switch acts on its own (spec 124, FR-013, User Story 6 scenarios 1, 2, 3, 5 and 7).
 *
 * The guard hid the login and the admin together, and only when protection was on. Now hiding
 * wp-login.php, hiding the admin from signed-out visitors and limiting failed sign-ins are three
 * switches, the own address works whenever any of them is on, and the recovery constant reopens the
 * default addresses whatever the switches say. Real requests are in
 * tests/Integration/Security/LoginSwitchesOverHttpTest.php.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;
use Corex\Config\Security\LoginProtection\LoginSwitches;

function switchedGuard(bool $login, bool $admin, bool $limits, bool $recovery = false): LoginRouteGuard
{
    return new LoginRouteGuard(
        LoginProtectionSettings::of(new LoginSwitches($login, $admin, $limits), customSlug: 'team-entry'),
        $recovery,
    );
}

/** Whether a signed-out visitor's /wp-admin/ is answered as a missing page. */
function hidesSignedOutAdmin(LoginRouteGuard $guard): bool
{
    return $guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'index.php', path: '/wp-admin/');
}

/** @return list<array{0:bool,1:bool,2:bool}> */
function everySwitchCombination(): array
{
    $combinations = [];
    foreach ([false, true] as $login) {
        foreach ([false, true] as $admin) {
            foreach ([false, true] as $limits) {
                $combinations[sprintf('login %s, admin %s, limits %s', $login ? 'hidden' : 'open', $admin ? 'hidden' : 'open', $limits ? 'on' : 'off')] = [$login, $admin, $limits];
            }
        }
    }

    return $combinations;
}

beforeEach(function () {
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $key === 'permalink_structure' ? '/%postname%/' : $default);
    Functions\when('is_multisite')->justReturn(false);
});

it('FR-013, scenario 1: hiding wp-login.php alone hides wp-login.php, serves the own address and leaves the admin to redirect', function () {
    $guard = switchedGuard(true, false, false);

    expect($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('hide')
        ->and($guard->entryPointFor('/team-entry/', isAdmin: false))->toBe('serve_login')
        ->and(hidesSignedOutAdmin($guard))->toBeFalse();
});

it('FR-013, scenario 2: hiding the admin alone hides a signed-out /wp-admin/ and leaves wp-login.php signing in', function () {
    $guard = switchedGuard(false, true, false);

    expect(hidesSignedOutAdmin($guard))->toBeTrue()
        ->and($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('pass')
        ->and($guard->entryPointFor('/wp-signup.php', isAdmin: false))->toBe('pass')
        ->and($guard->entryPointFor('/team-entry/', isAdmin: false))->toBe('serve_login');
});

it('FR-013, scenario 3: limiting failed sign-ins alone hides nothing and keeps the own address beside wp-login.php', function () {
    $guard = switchedGuard(false, false, true);

    expect($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('pass')
        ->and(hidesSignedOutAdmin($guard))->toBeFalse()
        ->and($guard->entryPointFor('/team-entry/', isAdmin: false))->toBe('serve_login');
});

it('FR-013: the own address works whenever any switch is on, and not when none is', function (bool $login, bool $admin, bool $limits) {
    $serves = switchedGuard($login, $admin, $limits)->entryPointFor('/team-entry/', isAdmin: false) === 'serve_login';

    expect($serves)->toBe($login || $admin || $limits);
})->with(everySwitchCombination());

it('FR-013: each switch hides only its own address', function (bool $login, bool $admin, bool $limits) {
    $guard = switchedGuard($login, $admin, $limits);

    expect($guard->entryPointFor('/wp-login.php', isAdmin: false) === 'hide')->toBe($login)
        ->and(hidesSignedOutAdmin($guard))->toBe($admin);
})->with(everySwitchCombination());

it('FR-013, scenario 5: admin-ajax.php, admin-post.php and options.php stay reachable in every combination', function (bool $login, bool $admin, bool $limits) {
    $guard = switchedGuard($login, $admin, $limits);

    foreach (['admin-ajax.php', 'admin-post.php', 'options.php'] as $script) {
        $path = '/wp-admin/' . $script;

        expect($guard->entryPointFor($path, isAdmin: true))->toBe('pass')
            ->and($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: $script, path: $path))->toBeFalse()
            ->and($guard->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: $script === 'admin-ajax.php', script: $script, path: $path))->toBeFalse();
    }
})->with(everySwitchCombination());

it('FR-013, scenario 7: the recovery constant reopens wp-login.php and the admin whatever the switches say, and keeps the own address', function (bool $login, bool $admin, bool $limits) {
    $guard = switchedGuard($login, $admin, $limits, recovery: true);

    expect($guard->entryPointFor('/wp-login.php', isAdmin: false))->toBe('pass')
        ->and(hidesSignedOutAdmin($guard))->toBeFalse()
        ->and($guard->entryPointFor('/team-entry/', isAdmin: false) === 'serve_login')->toBe($login || $admin || $limits);
})->with(everySwitchCombination());
