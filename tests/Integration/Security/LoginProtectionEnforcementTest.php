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
use Corex\Config\Security\LoginProtection\WpLoginAccounts;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;
use Corex\Config\Security\LoginProtection\WpLoginHashKey;
use Corex\Database\Schema\Migrator;
use Corex\Tests\Support\OptionRow;

/**
 * The address every attempt here is made from. It is in a range reserved for documentation, so no
 * real sign-in comes from it and every attempt recorded against it is one of these tests'.
 */
const LOGIN_ENFORCEMENT_TEST_IP = '203.0.113.42';

/**
 * Remove these tests' attempts, and only theirs. This used to be an unqualified `DELETE FROM`,
 * which on a developer's install is the whole sign-in history, active lockouts included.
 */
function forgetLoginEnforcementAttempts(Migrator $migrator): void
{
    global $wpdb;

    // Keyed with the site's own secret since spec 124 (FR-019), so found by the value the policy makes.
    $wpdb->delete(
        $migrator->fullName(LoginAttemptTable::NAME),
        ['network_hash' => \Corex\Boot::app()->container()->make(LoginProtectionPolicy::class)->networkHashOf(LOGIN_ENFORCEMENT_TEST_IP)],
    );
}

beforeEach(function () {
    $this->migrator = new Migrator();
    $this->migrator->create((new LoginAttemptTable())->schema());
    $this->attempts = new WpLoginAttemptStore($this->migrator);
    $this->settingsStore = new LoginProtectionSettingsStore();

    // The tests start from "nothing saved"; the install's own policy is put back afterwards. It
    // used to be deleted and left deleted.
    $this->previousLoginSettings = OptionRow::capture(LoginProtectionSettingsStore::OPTION);
    delete_option(LoginProtectionSettingsStore::OPTION);
    forgetLoginEnforcementAttempts($this->migrator);
    $this->previousServer = $_SERVER;
    $_SERVER['REMOTE_ADDR'] = LOGIN_ENFORCEMENT_TEST_IP;
    $_SERVER['HTTP_USER_AGENT'] = 'CoreX Test Browser';
});

afterEach(function () {
    // The row as it was found, its autoload included: it came back unloaded, which the store has not
    // written since it moved to loaded options (DECISIONS #366).
    OptionRow::restore(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
    forgetLoginEnforcementAttempts($this->migrator);
    $_SERVER = $this->previousServer;
});

function loginEnforcer(WpLoginAttemptStore $attempts, \Corex\Config\Security\LoginProtection\LoginProtectionSettings $settings): LoginProtectionEnforcer
{
    return new LoginProtectionEnforcer(
        new LoginProtectionService(new LoginProtectionPolicy($settings, new WpLoginHashKey()), $attempts, new WpLoginAccounts()),
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

/** This test's attempts, found by the keyed address they were made from. */
function loginEnforcementOutcomes(): array
{
    global $wpdb;

    return $wpdb->get_col($wpdb->prepare(
        'SELECT outcome FROM ' . (new Migrator())->fullName(LoginAttemptTable::NAME) . ' WHERE network_hash = %s ORDER BY id ASC',
        \Corex\Boot::app()->container()->make(LoginProtectionPolicy::class)->networkHashOf(LOGIN_ENFORCEMENT_TEST_IP),
    ));
}

// Spec 124 slice 4 review: recording successful sign-ins keeps its meaning. It records whenever its
// own setting and any switch are on, as it did while protection was on; only failures need limits.
it('records a successful sign-in whenever a switch is on and recording is on, and a failure only with limits', function (array $switches, bool $logging, array $outcomes) {
    $settings = \Corex\Config\Security\LoginProtection\LoginProtectionSettings::of(
        new \Corex\Config\Security\LoginProtection\LoginSwitches(...$switches),
        successfulLoginLogging: $logging,
    );
    $enforcer = loginEnforcer($this->attempts, $settings);
    $user = get_user_by('id', (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);

    $enforcer->recordFailure('owner');
    $enforcer->recordSuccess('owner', $user);

    expect(loginEnforcementOutcomes())->toBe($outcomes);
})->with([
    'the admin hidden alone' => [[false, true, false], true, ['success']],
    'wp-login.php hidden alone' => [[true, false, false], true, ['success']],
    'limits alone' => [[false, false, true], true, ['failed', 'success']],
    'every switch off' => [[false, false, false], true, []],
    'the admin hidden, recording off' => [[false, true, false], false, []],
]);
