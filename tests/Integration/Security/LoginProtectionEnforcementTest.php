<?php

/**
 * Login-protection persistence + real WordPress authentication enforcement (spec 068 FR-076/078).
 *
 * These prove the two gaps that made the Security Center's login policy ineffective are closed:
 * settings now persist to the option (so the guard can be enabled), and the enforcer actually blocks
 * a locked-out identity on the `authenticate` filter and records failures on `wp_login_failed`.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Config\Security\LoginProtection\LoginProtectionEnforcer;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;
use Corex\Database\Schema\Migrator;

beforeEach(function () {
    global $wpdb;
    $this->migrator = new Migrator();
    $this->migrator->create((new LoginAttemptTable())->schema());
    $this->attempts = new WpLoginAttemptStore($this->migrator);
    $this->settingsStore = new LoginProtectionSettingsStore();

    delete_option(LoginProtectionSettingsStore::OPTION);
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(LoginAttemptTable::NAME));
    $this->previousServer = $_SERVER;
    $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
    $_SERVER['HTTP_USER_AGENT'] = 'CoreX Test Browser';
});

afterEach(function () {
    global $wpdb;
    delete_option(LoginProtectionSettingsStore::OPTION);
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(LoginAttemptTable::NAME));
    $_SERVER = $this->previousServer;
});

function loginEnforcer(WpLoginAttemptStore $attempts, \Corex\Config\Security\LoginProtection\LoginProtectionSettings $settings): LoginProtectionEnforcer
{
    return new LoginProtectionEnforcer(
        new LoginProtectionService(new LoginProtectionPolicy($settings), $attempts),
        new ClientIpResolver($settings),
        $settings,
    );
}

it('persists login-protection settings to the option so the guard can be enabled', function () {
    $saved = $this->settingsStore->save([
        'enabled' => true,
        'custom_slug' => 'team-login',
        'threshold' => 3,
        'window_seconds' => 300,
        'lockout_seconds' => 900,
    ]);

    expect($saved->enabled)->toBeTrue()
        ->and($saved->customSlug)->toBe('team-login')
        ->and($saved->threshold)->toBe(3)
        ->and($this->settingsStore->current()->enabled)->toBeTrue()
        ->and(get_option(LoginProtectionSettingsStore::OPTION))->toBeArray();
});

it('blocks a locked-out identity on the authenticate filter after the threshold of failures', function () {
    $settings = $this->settingsStore->save(['enabled' => true, 'threshold' => 3, 'window_seconds' => 600, 'lockout_seconds' => 900]);
    $enforcer = loginEnforcer($this->attempts, $settings);

    // Not blocked before any failures.
    expect($enforcer->blockLockedOut(null, 'owner', 'secret'))->toBeNull();

    // Three failed sign-ins reach the threshold.
    $enforcer->recordFailure('owner');
    $enforcer->recordFailure('owner');
    $enforcer->recordFailure('owner');

    $blocked = $enforcer->blockLockedOut(null, 'owner', 'secret');

    expect($blocked)->toBeInstanceOf(WP_Error::class)
        ->and($blocked->get_error_code())->toBe('corex_login_locked')
        // A different identity from the same request is unaffected.
        ->and($enforcer->blockLockedOut(null, 'someone-else', 'secret'))->toBeNull();
});

it('does not enforce or record when login protection is disabled', function () {
    $settings = $this->settingsStore->save(['enabled' => false, 'threshold' => 2]);
    $enforcer = loginEnforcer($this->attempts, $settings);

    $enforcer->recordFailure('owner');
    $enforcer->recordFailure('owner');
    $enforcer->recordFailure('owner');

    // No lockout is possible while disabled; the authenticate result passes through untouched.
    expect($enforcer->blockLockedOut(null, 'owner', 'secret'))->toBeNull();
});
