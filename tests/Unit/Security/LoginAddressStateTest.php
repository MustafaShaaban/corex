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
