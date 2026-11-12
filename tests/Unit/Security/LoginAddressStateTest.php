<?php

/**
 * Whether the default login address is hidden, decided once (spec 114, US-SAFE-11).
 *
 * "Hidden" needs protection on, hiding on and recovery off; every other combination leaves
 * `/wp-login.php` serving its form. The login guard, the Security Center and the Overview ask
 * this one rule, so they cannot disagree.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginAddressState;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;

function loginSettings(bool $enabled, bool $hides): LoginProtectionSettings
{
    return new LoginProtectionSettings(
        enabled: $enabled,
        customSlug: 'team-login',
        blockDefaultEndpoints: $hides,
        threshold: 5,
        windowSeconds: 900,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: false,
    );
}

it('says hidden only with protection on, hiding on and recovery off', function (bool $enabled, bool $hides, bool $recovery, string $state) {
    expect(LoginAddressState::of(loginSettings($enabled, $hides), $recovery))->toBe($state);
})->with([
    'protection off, hiding on'           => [false, true, false, LoginAddressState::OFF],
    'protection off, hiding on, recovery' => [false, true, true, LoginAddressState::OFF],
    'protection off, hiding off'          => [false, false, false, LoginAddressState::OFF],
    'protection on, hiding off'           => [true, false, false, LoginAddressState::REACHABLE],
    'protection on, hiding off, recovery' => [true, false, true, LoginAddressState::REACHABLE],
    'protection on, hiding on, recovery'  => [true, true, true, LoginAddressState::RECOVERY],
    'protection on, hiding on'            => [true, true, false, LoginAddressState::HIDDEN],
]);

it('spec 124 FR-013: follows the "hide wp-login.php" switch alone, with any switch on meaning protection is on', function (bool $login, bool $admin, bool $limits, bool $recovery, string $state) {
    $settings = LoginProtectionSettings::of(new \Corex\Config\Security\LoginProtection\LoginSwitches($login, $admin, $limits), customSlug: 'team-login');

    expect(LoginAddressState::of($settings, $recovery))->toBe($state);
})->with([
    'all off'                          => [false, false, false, false, LoginAddressState::OFF],
    'all off, recovery'                => [false, false, false, true, LoginAddressState::OFF],
    'limits only'                      => [false, false, true, false, LoginAddressState::REACHABLE],
    'the admin hidden only'            => [false, true, false, false, LoginAddressState::REACHABLE],
    'wp-login.php hidden only'         => [true, false, false, false, LoginAddressState::HIDDEN],
    'wp-login.php hidden, recovery'    => [true, false, false, true, LoginAddressState::RECOVERY],
    'all on'                           => [true, true, true, false, LoginAddressState::HIDDEN],
]);
