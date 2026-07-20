<?php

/**
 * Unit tests for login-protection settings persistence.
 *
 * The store's read and write paths used to sanitise differently: save() fell back to a default
 * slug, current() did not. A stored slug of "!!!" therefore read back as "" — which served no
 * login URL while the default endpoint stayed hidden, locking the owner out entirely. That was
 * reproduced on a real install before these tests existed (DECISIONS #140).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSlug;

beforeEach(function () {
    $GLOBALS['corex_test_options'] = [];
    Functions\when('__')->returnArg();
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    // Close enough to the real thing for slug purposes: lowercase, strip anything that is not
    // alphanumeric or a hyphen, collapse and trim hyphens.
    Functions\when('sanitize_title')->alias(static function (string $title): string {
        $slug = strtolower($title);
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);

        return trim((string) preg_replace('/-+/', '-', $slug), '-');
    });
});

it('reads back exactly the slug it stored', function () {
    $store = new LoginProtectionSettingsStore();
    $saved = $store->save(['enabled' => true, 'custom_slug' => 'team-entry']);

    expect($saved->customSlug)->toBe('team-entry')
        ->and($store->current()->customSlug)->toBe('team-entry');
});

it('never reads back an unusable slug, however the option was written', function () {
    $store = new LoginProtectionSettingsStore();

    // Bypass save() the way a hand-edited option, a migration, or WP-CLI would.
    // '-leading' is included deliberately: it is recoverable (it sanitises to 'leading'), so the
    // invariant is that the result is *usable*, not that it is always the default.
    foreach (['!!!', '', 'ab', 'wp-admin', '-leading', 'UPPER CASE', str_repeat('x', 200)] as $hostile) {
        $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = [
            'enabled'     => true,
            'custom_slug' => $hostile,
        ];

        expect(LoginSlug::isValid($store->current()->customSlug))->toBeTrue();
    }
});

it('falls back to the default only when the stored slug cannot be salvaged', function () {
    $store = new LoginProtectionSettingsStore();

    $resolve = static function (string $stored) use ($store): string {
        $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = [
            'enabled'     => true,
            'custom_slug' => $stored,
        ];

        return $store->current()->customSlug;
    };

    expect($resolve('!!!'))->toBe(LoginSlug::DEFAULT)
        ->and($resolve('ab'))->toBe(LoginSlug::DEFAULT)
        ->and($resolve('wp-admin'))->toBe(LoginSlug::DEFAULT)
        // Salvageable input keeps the owner's intent rather than silently reverting.
        ->and($resolve('-leading'))->toBe('leading')
        ->and($resolve('Team Entry'))->toBe('team-entry');
});

it('does not throw when the stored settings are unusable', function () {
    // The container builds LoginProtectionSettings on every make(); a throw here took the whole
    // ConfigServiceProvider down with it and silently disabled login protection.
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = [
        'enabled'        => true,
        'custom_slug'    => 'ab',
        'threshold'      => 0,
        'window_seconds' => -5,
        'retain_days'    => 0,
    ];

    $settings = (new LoginProtectionSettingsStore())->current();

    expect($settings->customSlug)->toBe(LoginSlug::DEFAULT)
        ->and($settings->threshold)->toBeGreaterThanOrEqual(1)
        ->and($settings->windowSeconds)->toBeGreaterThanOrEqual(1)
        ->and($settings->retainDays)->toBeGreaterThanOrEqual(1);
});

it('falls back to the default slug when none was supplied', function () {
    $store = new LoginProtectionSettingsStore();

    expect($store->save(['enabled' => true])->customSlug)->toBe(LoginSlug::DEFAULT)
        ->and($store->save(['enabled' => true, 'custom_slug' => '!!!'])->customSlug)->toBe(LoginSlug::DEFAULT);
});

it('survives a stored option that is not an array', function () {
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = 'corrupted';

    expect((new LoginProtectionSettingsStore())->current()->customSlug)->toBe(LoginSlug::DEFAULT);
});
