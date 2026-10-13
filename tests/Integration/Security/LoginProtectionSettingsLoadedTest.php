<?php

/**
 * Integration tests: the login protection settings are stored where WordPress loads them with the
 * site's options, and a site that saved them before that keeps its hidden login address
 * (DECISIONS #366).
 *
 * The settings are read as CoreX boots, on every request, and were saved with autoload off: one
 * query a request. They decide whether the login address is hidden and which slug answers, so the
 * row of a site that saved them under an older release has to go on being read while it is still
 * stored unloaded, and has to hold the same settings once it is turned over.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Cli\Commands\SecurityResetLoginCommand;
use Corex\Cli\Commands\SecurityTrustedProxiesCommand;
use Corex\Config\Security\LoginProtection\LoginLockoutStore;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;
use Corex\Config\Security\LoginProtection\LoginUrl;
use Corex\Tests\Support\LoadedOptionsRun;
use Corex\Tests\Support\OptionRow;

const HIDDEN_LOGIN_AS_AN_OLDER_RELEASE_STORED_IT = [
    'enabled' => true,
    'custom_slug' => 'team-door',
    'block_default_endpoints' => true,
];

beforeEach(function () {
    $this->settingsRow = OptionRow::capture(LoginProtectionSettingsStore::OPTION);
    $this->store = new LoginProtectionSettingsStore();
});

afterEach(function () {
    OptionRow::restore(LoginProtectionSettingsStore::OPTION, $this->settingsRow);
});

it('keeps a hidden login address and its slug while the row is unloaded, and once it is turned over', function () {
    OptionRow::storeUnloaded(LoginProtectionSettingsStore::OPTION, HIDDEN_LOGIN_AS_AN_OLDER_RELEASE_STORED_IT);

    $unloaded = $this->store->current();

    LoadedOptionsRun::onACronRequest();

    $turnedOver = $this->store->current();

    expect(OptionRow::autoload(LoginProtectionSettingsStore::OPTION))->toBe('on')
        ->and($unloaded->enabled)->toBeTrue()
        ->and($unloaded->customSlug)->toBe('team-door')
        ->and($turnedOver)->toEqual($unloaded)
        ->and(LoginUrl::forSettings($turnedOver))->toContain('team-door')
        ->and((new LoginRouteGuard($turnedOver, unguarded: false))->entryPointFor('/wp-login.php', isAdmin: false))
        ->toBe('hide');
});

it('gives a site that never saved the settings a row that says so, and protects it as before', function () {
    delete_option(LoginProtectionSettingsStore::OPTION);
    $neverSaved = $this->store->current();

    LoadedOptionsRun::onACronRequest();

    expect(OptionRow::autoload(LoginProtectionSettingsStore::OPTION))->toBe('on')
        ->and($neverSaved->enabled)->toBeFalse()
        ->and($this->store->current())->toEqual($neverSaved);
});

it('stores a save loaded, also when it changes nothing in a row stored unloaded', function () {
    $this->store->save(HIDDEN_LOGIN_AS_AN_OLDER_RELEASE_STORED_IT);
    OptionRow::storeUnloaded(
        LoginProtectionSettingsStore::OPTION,
        get_option(LoginProtectionSettingsStore::OPTION),
    );

    $saved = $this->store->save(HIDDEN_LOGIN_AS_AN_OLDER_RELEASE_STORED_IT);

    expect(OptionRow::autoload(LoginProtectionSettingsStore::OPTION))->toBe('on')
        ->and($saved->customSlug)->toBe('team-door');
});

it('stores the settings loaded when the reset-login command writes them', function () {
    OptionRow::storeUnloaded(LoginProtectionSettingsStore::OPTION, HIDDEN_LOGIN_AS_AN_OLDER_RELEASE_STORED_IT);

    // Every lockout still active at this moment is released, on the whole install. Decades away,
    // none is, so a developer's real lockouts are not released by running the suite.
    $command = new SecurityResetLoginCommand(\Corex\Boot::app()->container()->make(LoginLockoutStore::class));
    $command->restore(new DateTimeImmutable('2099-01-01T12:00:00+00:00'));

    expect(OptionRow::autoload(LoginProtectionSettingsStore::OPTION))->toBe('on')
        ->and($this->store->current()->enabled)->toBeFalse();
});

it('stores the settings loaded when the trusted-proxies command writes them', function () {
    OptionRow::storeUnloaded(LoginProtectionSettingsStore::OPTION, HIDDEN_LOGIN_AS_AN_OLDER_RELEASE_STORED_IT);

    $refused = \Corex\Boot::app()->container()->make(SecurityTrustedProxiesCommand::class)->trust(['203.0.113.7']);

    $settings = $this->store->current();

    expect($refused)->toBe([])
        ->and(OptionRow::autoload(LoginProtectionSettingsStore::OPTION))->toBe('on')
        ->and($settings->trustedProxyRanges)->toBe(['203.0.113.7'])
        // What the command did not name is as it was.
        ->and($settings->customSlug)->toBe('team-door');
});
