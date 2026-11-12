<?php

/**
 * The login settings store reads both stored shapes and writes both (spec 124, FR-013, FR-014,
 * User Story 6 scenarios 4 and 6, the edge cases about an older screen and an older CoreX).
 *
 * The previous release stored `enabled` and `block_default_endpoints`. This one stores the three
 * switches (`hide_wp_login`, `hide_admin`, `limit_attempts`) and, beside them, the two old keys
 * written so an older CoreX does the closest safe thing (see LoginSwitchesTest).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsRefused;
use Corex\Config\Security\LoginProtection\LoginSlug;

beforeEach(function () {
    $GLOBALS['corex_test_options'] = [];
    $GLOBALS['corex_test_option_writes'] = 0;
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('wp_set_option_autoload')->justReturn(true);
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;
        $GLOBALS['corex_test_option_writes']++;

        return true;
    });
    Functions\when('sanitize_title')->alias(static function (string $title): string {
        $slug = strtolower($title);
        $slug = (string) preg_replace('/[^a-z0-9\-]+/', '-', $slug);

        return trim((string) preg_replace('/-+/', '-', $slug), '-');
    });
});

/** What the previous release's save wrote: every key, always (its `LoginProtectionSettingsStore::save()`). */
function previousReleaseShape(bool $enabled, bool $hide, string $slug = 'corex-login'): array
{
    return [
        'enabled'                  => $enabled,
        'custom_slug'              => $slug,
        'block_default_endpoints'  => $hide,
        'threshold'                => 5,
        'window_seconds'           => 300,
        'lockout_seconds'          => 900,
        'trusted_proxy_mode'       => false,
        'trusted_proxy_ranges'     => [],
        'retain_days'              => 30,
        'successful_login_logging' => true,
    ];
}

/** @return array{0:bool,1:bool,2:bool} */
function switchesOf(\Corex\Config\Security\LoginProtection\LoginProtectionSettings $settings): array
{
    return [$settings->hideWpLogin, $settings->hideAdmin, $settings->limitAttempts];
}

it('FR-014, scenario 6: reads each shape the previous release stored in its old meaning, and does not rewrite it', function (array $stored, array $switches, string $slug) {
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = $stored;

    $settings = (new LoginProtectionSettingsStore())->current();

    expect(switchesOf($settings))->toBe($switches)
        ->and($settings->customSlug)->toBe($slug)
        ->and($GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION])->toBe($stored)
        ->and($GLOBALS['corex_test_option_writes'])->toBe(0);
})->with([
    'protection on, hiding on (Muva and Perego)' => [previousReleaseShape(true, true), [true, true, true], 'corex-login'],
    'protection on, hiding on, its own address' => [previousReleaseShape(true, true, 'team-entry'), [true, true, true], 'team-entry'],
    'protection on, hiding off' => [previousReleaseShape(true, false, 'team-entry'), [false, false, true], 'team-entry'],
    'protection off, hiding stored on' => [previousReleaseShape(false, true), [false, false, false], 'corex-login'],
    'protection off, hiding stored off' => [previousReleaseShape(false, false), [false, false, false], 'corex-login'],
    'only the two keys, as a hand-written option' => [['enabled' => true, 'custom_slug' => 'team-entry'], [true, true, true], 'team-entry'],
    'nothing saved yet' => [[], [false, false, false], LoginSlug::DEFAULT],
    'what the recovery command of the previous release left' => [['enabled' => false, 'block_default_endpoints' => false, 'custom_slug' => LoginSlug::DEFAULT], [false, false, false], LoginSlug::DEFAULT],
]);

it('FR-014: keeps the previous release\'s two keys as they are stored, for whatever reads them', function () {
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = previousReleaseShape(false, true);

    $settings = (new LoginProtectionSettingsStore())->current();

    expect($settings->enabled)->toBeFalse()
        ->and($settings->blockDefaultEndpoints)->toBeTrue();
});

it('FR-013, FR-014: writes the three switches and the previous release\'s two keys', function (bool $login, bool $admin, bool $limits, bool $enabled, bool $hide) {
    $store = new LoginProtectionSettingsStore();

    $saved = $store->save([
        'hide_wp_login'  => $login,
        'hide_admin'     => $admin,
        'limit_attempts' => $limits,
        'custom_slug'    => 'team-entry',
    ]);
    $stored = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];

    expect([$stored['hide_wp_login'], $stored['hide_admin'], $stored['limit_attempts']])->toBe([$login, $admin, $limits])
        ->and($stored['enabled'])->toBe($enabled)
        ->and($stored['block_default_endpoints'])->toBe($hide)
        ->and(switchesOf($saved))->toBe([$login, $admin, $limits])
        ->and(switchesOf($store->current()))->toBe([$login, $admin, $limits]);
})->with([
    'all off'                   => [false, false, false, false, false],
    'limits only'               => [false, false, true, true, false],
    'hide wp-login.php only'    => [true, false, false, true, false],
    'hide the admin only'       => [false, true, false, true, false],
    'hide wp-login.php, limits' => [true, false, true, true, false],
    'hide the admin, limits'    => [false, true, true, true, false],
    'hide both, no limits'      => [true, true, false, true, true],
    'all on'                    => [true, true, true, true, true],
]);

it('FR-014: what a save wrote is what an older CoreX reads: its keys say what the switches do on that release', function (bool $login, bool $admin, bool $limits) {
    (new LoginProtectionSettingsStore())->save([
        'hide_wp_login'  => $login,
        'hide_admin'     => $admin,
        'limit_attempts' => $limits,
        'custom_slug'    => 'team-entry',
    ]);
    $stored = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];

    // The previous release's own reading: protection on serves the address and limits attempts,
    // and with it "hide" hides wp-login.php and the admin together.
    $olderServesAddress = (bool) $stored['enabled'];
    $olderHides = (bool) $stored['enabled'] && (bool) $stored['block_default_endpoints'];

    expect($olderServesAddress)->toBe($login || $admin || $limits)
        ->and($olderHides)->toBe($login && $admin)
        ->and($stored['custom_slug'])->toBe('team-entry');
})->with([
    [false, false, false], [false, false, true], [true, false, false], [false, true, false],
    [true, false, true], [false, true, true], [true, true, false], [true, true, true],
]);

it('FR-014, edge case: a save from an older screen that sends `enabled` and `block_default_endpoints` is read in the old meaning', function (array $input, array $switches) {
    $saved = (new LoginProtectionSettingsStore())->save($input + ['custom_slug' => 'team-entry']);

    expect(switchesOf($saved))->toBe($switches);
})->with([
    'protection on, hiding on' => [['enabled' => true, 'block_default_endpoints' => true], [true, true, true]],
    'protection on, hiding off' => [['enabled' => true, 'block_default_endpoints' => false], [false, false, true]],
    'protection on, hiding not sent (the old default: on)' => [['enabled' => true], [true, true, true]],
    'protection off, hiding on' => [['enabled' => false, 'block_default_endpoints' => true], [false, false, false]],
    'nothing about protection sent' => [[], [false, false, false]],
]);

it('FR-014: a switch left out of a save that names the others is off, never on', function () {
    $saved = (new LoginProtectionSettingsStore())->save(['hide_admin' => true, 'custom_slug' => 'team-entry']);

    expect(switchesOf($saved))->toBe([false, true, false]);
});

it('FR-014, edge case: the old keys changed by an older CoreX after a save win over the switches beside them', function () {
    // An older CoreX's recovery command sets `enabled` and `block_default_endpoints` off and keeps
    // every other key it finds, the three switches included. Reading the switches then would hide the
    // login again the moment the site runs this release once more.
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = [
        'hide_wp_login'            => true,
        'hide_admin'               => true,
        'limit_attempts'           => true,
        'enabled'                  => false,
        'block_default_endpoints'  => false,
        'custom_slug'              => LoginSlug::DEFAULT,
    ];

    expect(switchesOf((new LoginProtectionSettingsStore())->current()))->toBe([false, false, false]);
});

it('FR-014: an older screen that saved after this release dropped the switches, and its two keys are read', function () {
    $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION] = previousReleaseShape(true, false, 'team-entry');

    expect(switchesOf((new LoginProtectionSettingsStore())->current()))->toBe([false, false, true]);
});

it('FR-013, scenario 4: refuses to hide wp-login.php with the address cleared or unusable, with the reason, and changes nothing', function (array $input, string $reason) {
    $store = new LoginProtectionSettingsStore();
    $store->save(['hide_wp_login' => true, 'hide_admin' => false, 'limit_attempts' => true, 'custom_slug' => 'team-entry']);
    $before = $GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION];
    $writes = $GLOBALS['corex_test_option_writes'];

    try {
        $store->save($input);
        $this->fail('The save was not refused.');
    } catch (LoginSettingsRefused $refused) {
        expect($refused->reason)->toBe($reason)
            ->and($refused->field)->toBe('custom_slug');
    }

    expect($GLOBALS['corex_test_options'][LoginProtectionSettingsStore::OPTION])->toBe($before)
        ->and($GLOBALS['corex_test_option_writes'])->toBe($writes);
})->with([
    'cleared, the switches' => [['hide_wp_login' => true, 'custom_slug' => ''], LoginSlug::REASON_EMPTY],
    'cleared, from an older screen' => [['enabled' => true, 'block_default_endpoints' => true, 'custom_slug' => ''], LoginSlug::REASON_EMPTY],
    'nothing left once cleaned' => [['hide_wp_login' => true, 'custom_slug' => '!!!'], LoginSlug::REASON_EMPTY],
    'reserved' => [['hide_wp_login' => true, 'custom_slug' => 'wp-admin'], LoginSlug::REASON_RESERVED],
    'too short' => [['hide_wp_login' => true, 'custom_slug' => 'ab'], LoginSlug::REASON_FORMAT],
]);

it('FR-013: asks for an address only to hide wp-login.php; the other switches take the default for a cleared one', function (array $input) {
    $saved = (new LoginProtectionSettingsStore())->save($input + ['custom_slug' => '']);

    expect($saved->customSlug)->toBe(LoginSlug::DEFAULT);
})->with([
    'hide the admin' => [['hide_admin' => true]],
    'limits' => [['limit_attempts' => true]],
    'all off' => [[]],
]);

it('FR-013: hiding wp-login.php with no address sent takes the default address', function () {
    $saved = (new LoginProtectionSettingsStore())->save(['hide_wp_login' => true]);

    expect($saved->hideWpLogin)->toBeTrue()
        ->and($saved->customSlug)->toBe(LoginSlug::DEFAULT);
});
