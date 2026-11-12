<?php

/**
 * The three login switches and the two keys of the release before them (spec 124, FR-013, FR-014).
 *
 * The release before stored one "protection on" key (`enabled`) and one "hide" key
 * (`block_default_endpoints`) that hid the login and the admin together, and only with protection on.
 * These tests hold how those two keys are read as the three switches, and how the three switches are
 * written back into them for a site that later runs that release again.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginSwitches;

it('FR-014, scenario 6: reads the previous release\'s two keys in their old meaning', function (bool $enabled, bool $hide, array $switches) {
    $read = LoginSwitches::fromPreviousRelease($enabled, $hide);

    expect([$read->hideWpLogin, $read->hideAdmin, $read->limitAttempts])->toBe($switches);
})->with([
    'protection on, hiding on: all three on' => [true, true, [true, true, true]],
    'protection on, hiding off: limits on, its own address beside wp-login.php' => [true, false, [false, false, true]],
    'protection off, hiding stored on: all three off' => [false, true, [false, false, false]],
    'protection off, hiding stored off: all three off' => [false, false, [false, false, false]],
]);

/*
 * The old-key table. An older CoreX reads `enabled` as "serve the own address and limit attempts"
 * and `block_default_endpoints` (with `enabled`) as "hide wp-login.php and the admin together". Each
 * combination is written so that older release does the closest thing that hides nothing the owner
 * did not ask to hide: it hides only when both are hidden, and serves the own address whenever any
 * switch is on, since that is the address the owner signs in at.
 */
it('FR-014: writes the previous release\'s keys so an older CoreX does the closest safe thing', function (bool $login, bool $admin, bool $limits, bool $enabled, bool $hide) {
    $switches = new LoginSwitches(hideWpLogin: $login, hideAdmin: $admin, limitAttempts: $limits);

    expect($switches->previousEnabled())->toBe($enabled)
        ->and($switches->previousBlockDefaultEndpoints())->toBe($hide);
})->with([
    'all off'                     => [false, false, false, false, false],
    'limits only'                 => [false, false, true, true, false],
    'hide wp-login.php only'      => [true, false, false, true, false],
    'hide the admin only'         => [false, true, false, true, false],
    'hide wp-login.php, limits'   => [true, false, true, true, false],
    'hide the admin, limits'      => [false, true, true, true, false],
    'hide both, no limits'        => [true, true, false, true, true],
    'all on'                      => [true, true, true, true, true],
]);

it('FR-014: never writes keys that make an older CoreX hide what a switch does not hide', function (bool $login, bool $admin, bool $limits) {
    $switches = new LoginSwitches(hideWpLogin: $login, hideAdmin: $admin, limitAttempts: $limits);
    $olderHides = $switches->previousEnabled() && $switches->previousBlockDefaultEndpoints();

    expect($olderHides)->toBe($olderHides && $login && $admin);
})->with([
    [false, false, false], [false, false, true], [true, false, false], [false, true, false],
    [true, false, true], [false, true, true], [true, true, false], [true, true, true],
]);

it('FR-014: a combination the previous release could store reads back as itself when written', function (bool $enabled, bool $hide) {
    $read = LoginSwitches::fromPreviousRelease($enabled, $hide);

    expect($read->previousEnabled())->toBe($enabled)
        ->and($read->previousBlockDefaultEndpoints())->toBe($enabled && $hide);
})->with([[true, true], [true, false], [false, true], [false, false]]);

it('FR-013: says whether any switch is on, which is when the site\'s own login address works', function (bool $login, bool $admin, bool $limits, bool $any) {
    expect((new LoginSwitches($login, $admin, $limits))->any())->toBe($any);
})->with([
    'none' => [false, false, false, false],
    'limits' => [false, false, true, true],
    'login' => [true, false, false, true],
    'admin' => [false, true, false, true],
]);

it('FR-013: all three off', function () {
    $off = LoginSwitches::off();

    expect([$off->hideWpLogin, $off->hideAdmin, $off->limitAttempts])->toBe([false, false, false]);
});
