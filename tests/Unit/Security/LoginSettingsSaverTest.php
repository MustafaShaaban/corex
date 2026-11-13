<?php

/**
 * Two saves of the login settings that overlap keep both changes (spec 124, FR-015; DECISIONS #629).
 *
 * The lock makes one save wait for the other, but a save that decided what to write from what it read
 * before it had the lock still writes the old value of every switch it did not send. So what a request
 * changes is decided from the option as read once the lock is had. The fake `$wpdb` lets "another
 * save" land the moment this one is granted the lock: after anything read early, before the read that
 * counts.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsSaver;
use Corex\Config\Security\LoginProtection\LoginSlugAvailability;
use Corex\Tests\Support\NamedLockWpdb;

beforeEach(function () {
    $GLOBALS['corex_test_options'] = [LoginProtectionSettingsStore::OPTION => [
        'hide_wp_login'           => false,
        'hide_admin'              => false,
        'limit_attempts'          => true,
        'enabled'                 => true,
        'block_default_endpoints' => false,
        'custom_slug'             => 'team-door',
        'threshold'               => 5,
        'window_seconds'          => 300,
    ]];
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('wp_set_option_autoload')->justReturn(true);
    Functions\when('wp_cache_delete')->justReturn(true);
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    Functions\when('sanitize_title')->alias(static fn (string $title): string => strtolower(trim($title)));

    $this->wpdb = new NamedLockWpdb();
    $GLOBALS['wpdb'] = $this->wpdb;
    $this->store = new LoginProtectionSettingsStore();
    $this->saver = new LoginSettingsSaver($this->store, new LoginSlugAvailability());

    // Another save, B, turns the limits off while this one, A, waits for the lock.
    $this->anotherSaveMeanwhile = function (array $body): void {
        $this->wpdb->onGranted = function () use ($body): void {
            $other = new NamedLockWpdb();
            $mine = $GLOBALS['wpdb'];
            $GLOBALS['wpdb'] = $other;
            try {
                expect((new LoginSettingsSaver(new LoginProtectionSettingsStore(), new LoginSlugAvailability()))->save($body)->isRefused())->toBeFalse();
            } finally {
                $GLOBALS['wpdb'] = $mine;
            }
        };
    };
});

it('FR-015: keeps both switches when two saves overlap, each sending one', function () {
    ($this->anotherSaveMeanwhile)(['limit_attempts' => false]);

    $saved = $this->saver->save(['hide_admin' => true]);
    $stored = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];

    expect($saved->isRefused())->toBeFalse()
        ->and([$stored['hide_wp_login'], $stored['hide_admin'], $stored['limit_attempts']])->toBe([false, true, false])
        ->and($saved->settings->limitAttempts)->toBeFalse()
        ->and($saved->settings->hideAdmin)->toBeTrue();
});

it('FR-015: judges "no change" against the option as read under the lock', function () {
    // B turns the limits off; A sends them on, which was the value when A began. A's on is a change
    // from what is stored when A writes, so it is written.
    ($this->anotherSaveMeanwhile)(['limit_attempts' => false]);

    $this->saver->save(['limit_attempts' => true]);

    expect($GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION]['limit_attempts'])->toBeTrue();
});

it('FR-015: keeps both changes when two saves overlap on different fields', function () {
    ($this->anotherSaveMeanwhile)(['max_attempts' => 9]);

    $this->saver->save(['hide_admin' => true]);
    $stored = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];

    expect($stored['threshold'])->toBe(9)
        ->and($stored['hide_admin'])->toBeTrue();
});

it('FR-014: reads the previous screen\'s old pair against the option as read under the lock', function () {
    // B hides wp-login.php alone; A, the previous screen, sends the old pair it read before B
    // (on, not hidden) with Max attempts. Against the fresh read the old pair is unchanged, so A
    // leaves B's switches as they are.
    ($this->anotherSaveMeanwhile)(['hide_wp_login' => true, 'limit_attempts' => false]);

    $this->saver->save(['enabled' => true, 'block_default_endpoints' => false, 'max_attempts' => 8]);
    $stored = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];

    expect([$stored['hide_wp_login'], $stored['hide_admin'], $stored['limit_attempts']])->toBe([true, false, false])
        ->and($stored['threshold'])->toBe(8);
});

it('writes nothing when the request is refused, and lets the lock go', function () {
    $before = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];

    $outcome = $this->saver->save(['lockout_minutes' => 0]);

    expect($outcome->isRefused())->toBeTrue()
        ->and($GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION])->toBe($before)
        ->and($this->wpdb->held)->toBeNull();
});
