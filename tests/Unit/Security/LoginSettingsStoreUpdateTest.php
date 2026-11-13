<?php

/**
 * The login settings store changes only what it is given (spec 124, FR-015, User Story 7 scenario 2).
 *
 * `update()` writes the keys it is handed over the option as stored and leaves every other key as it
 * was, whatever wrote it: a 30-second window set from the command line, a slug or an older CoreX's
 * keys. The switches are written in both shapes only when they are given (FR-014).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsBusy;
use Corex\Config\Security\LoginProtection\LoginSettingsRefused;
use Corex\Config\Security\LoginProtection\LoginSwitches;
use Corex\Tests\Support\NamedLockWpdb;

/** @return array<string,mixed> the stored option, its keys sorted so a comparison reads values only */
function loginUpdateStored(): array
{
    return loginUpdateSorted($GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION]);
}

/** @param array<string,mixed> $values */
function loginUpdateSorted(array $values): array
{
    ksort($values);

    return $values;
}

beforeEach(function () {
    $GLOBALS['corex_test_options'] = [];
    $GLOBALS['corex_test_option_writes'] = 0;
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('wp_set_option_autoload')->justReturn(true);
    Functions\when('wp_cache_delete')->justReturn(true);
    $this->wpdb = new NamedLockWpdb();
    $GLOBALS['wpdb'] = $this->wpdb;
    $this->lockHeldAtWrite = null;
    Functions\when('update_option')->alias(function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;
        $GLOBALS['corex_test_option_writes']++;
        $this->lockHeldAtWrite = $this->wpdb->held;

        return true;
    });
    Functions\when('sanitize_title')->alias(static function (string $title): string {
        $slug = strtolower($title);
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);

        return trim((string) preg_replace('/-+/', '-', $slug), '-');
    });

    $this->stored = [
        'enabled'                 => true,
        'custom_slug'             => 'team-door',
        'block_default_endpoints' => true,
        'threshold'               => 5,
        'window_seconds'          => 30,
        'lockout_seconds'         => 900,
        'trusted_proxy_mode'      => false,
        'trusted_proxy_ranges'    => [],
        'retain_days'             => 30,
        'a_key_from_elsewhere'    => 'kept',
    ];
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = $this->stored;
    $this->store = new LoginProtectionSettingsStore();
});

it('keeps a 30-second window when only the maximum attempts change (FR-015, scenario 2)', function () {
    $saved = $this->store->update(['threshold' => 7]);

    expect(loginUpdateStored())->toBe(loginUpdateSorted(['threshold' => 7] + $this->stored))
        ->and($saved->windowSeconds)->toBe(30)
        ->and($saved->threshold)->toBe(7);
});

it('FR-015: writes under a named lock for this site, and lets it go', function () {
    $this->store->update(['threshold' => 7]);

    expect($this->lockHeldAtWrite)->toStartWith('corex_login_settings_')
        ->and($this->wpdb->held)->toBeNull()
        ->and($this->wpdb->statements[0])->toContain('GET_LOCK')
        ->and(end($this->wpdb->statements))->toContain('RELEASE_LOCK');
});

it('FR-015: names the lock by database and options table, so each site of a network has its own', function () {
    $this->store->update(['threshold' => 7]);
    $first = $this->lockHeldAtWrite;

    $this->wpdb->options = 'wp_2_options';
    $this->store->update(['threshold' => 8]);

    expect($this->lockHeldAtWrite)->not->toBe($first);
});

it('FR-015: writes nothing when another save holds the lock, and says so', function () {
    $this->wpdb->grants = false;

    expect(fn () => $this->store->update(['threshold' => 7]))->toThrow(LoginSettingsBusy::class);

    expect($GLOBALS['corex_test_option_writes'])->toBe(0);
});

it('lets the lock go when the write is refused', function () {
    expect(fn () => $this->store->update(['custom_slug' => ''], new LoginSwitches(true, true, true)))
        ->toThrow(LoginSettingsRefused::class);

    expect($this->wpdb->held)->toBeNull();
});

it('writes nothing when it is given nothing to change', function () {
    $this->store->update([]);

    expect($GLOBALS['corex_test_option_writes'])->toBe(0);
});

it('writes the switches in both shapes when they are given, and nothing else (FR-014)', function () {
    $this->store->update([], new LoginSwitches(hideWpLogin: true, hideAdmin: false, limitAttempts: true));

    expect(loginUpdateStored())->toBe(loginUpdateSorted([
        'enabled'                 => true,
        'custom_slug'             => 'team-door',
        'block_default_endpoints' => false,
        'hide_wp_login'           => true,
        'hide_admin'              => false,
        'limit_attempts'          => true,
    ] + $this->stored));
});

it('leaves the stored switches and the old keys as they are when no switch is given (FR-014, FR-015)', function () {
    $this->store->update(['retain_days' => 60]);

    $stored = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];
    expect($stored)->not->toHaveKey('hide_wp_login')
        ->and($stored['enabled'])->toBeTrue()
        ->and($stored['block_default_endpoints'])->toBeTrue();
});

it('ignores a key it does not keep', function () {
    $this->store->update(['threshold' => 6, 'not_a_setting' => 'x']);

    expect($GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION])->not->toHaveKey('not_a_setting');
});

it('refuses hiding wp-login.php behind an unusable address and writes nothing (FR-013)', function () {
    expect(fn () => $this->store->update(['custom_slug' => ''], new LoginSwitches(true, true, true)))
        ->toThrow(LoginSettingsRefused::class);

    expect($GLOBALS['corex_test_option_writes'])->toBe(0);
});
