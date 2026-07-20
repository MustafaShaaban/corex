<?php

/**
 * Integration tests for login-slug persistence against real WordPress.
 *
 * The unit suite stubs sanitize_title() with an approximation. That stub is the weak point: if
 * real WordPress normalises a slug differently than the stub, the unit tests stay green while an
 * owner is locked out in production. These tests run the same invariants through the real
 * function, so a divergence fails here rather than on someone's site.
 *
 * Both hostile inputs below were reproduced as live lockouts before the fix (DECISIONS #140).
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSlug;

beforeEach(function () {
    $this->previousLoginSettings = get_option(LoginProtectionSettingsStore::OPTION, null);
});

afterEach(function () {
    if ($this->previousLoginSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);

        return;
    }

    update_option(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
});

it('resolves every hostile stored slug to a usable one through real sanitize_title', function (string $stored) {
    update_option(
        LoginProtectionSettingsStore::OPTION,
        ['enabled' => true, 'custom_slug' => $stored, 'block_default_endpoints' => true],
        false,
    );

    $slug = (new LoginProtectionSettingsStore())->current()->customSlug;

    expect(LoginSlug::isValid($slug))->toBeTrue();
})->with([
    'unsanitizable (was: total lockout)' => '!!!',
    'too short (was: provider outage + fail-open)' => 'ab',
    'empty' => '',
    'reserved' => 'wp-admin',
    'leading hyphen' => '-leading',
    'spaces and case' => 'Team Entry',
    'accented' => 'café-entrée',
    'over length' => 'a-very-long-slug-that-keeps-going-and-going-and-going-and-going-and-going-and-going-and-going',
]);

it('builds the settings from a hostile stored slug without throwing', function () {
    // The container rebuilds LoginProtectionSettings on every make(). A throw here does not
    // fatal — provider boot catches it (ProviderRepository) — it silently drops the entire
    // ConfigServiceProvider and disables login protection while the option still says enabled.
    update_option(
        LoginProtectionSettingsStore::OPTION,
        ['enabled' => true, 'custom_slug' => 'ab', 'threshold' => 0, 'retain_days' => -1],
        false,
    );

    $settings = (new LoginProtectionSettingsStore())->current();

    expect($settings)->toBeInstanceOf(LoginProtectionSettings::class)
        ->and($settings->customSlug)->toBe(LoginSlug::DEFAULT)
        ->and($settings->enabled)->toBeTrue();
});

it('reads back exactly what it saved for a slug an owner would choose', function (string $chosen, string $expected) {
    $store = new LoginProtectionSettingsStore();
    $saved = $store->save(['enabled' => true, 'custom_slug' => $chosen]);

    expect($saved->customSlug)->toBe($expected)
        ->and($store->current()->customSlug)->toBe($expected);
})->with([
    'plain' => ['team-entry', 'team-entry'],
    'uppercase normalises' => ['Team-Entry', 'team-entry'],
    'spaces normalise' => ['team entry', 'team-entry'],
]);
