<?php

/**
 * Unit tests for the login recovery CLI command seam.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Commands\SecurityResetLoginCommand;
use Corex\Config\Security\LoginProtection\LoginLockoutStore;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Tests\Support\NamedLockWpdb;

function corexRecoveryOptions(array &$options): NamedLockWpdb
{
    $wpdb = new NamedLockWpdb();
    $GLOBALS['wpdb'] = $wpdb;
    $GLOBALS['corex_recovery_lock_at_write'] = null;
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $options[$key] ?? $default);
    Functions\when('wp_set_option_autoload')->justReturn(true);
    Functions\when('wp_cache_delete')->justReturn(true);
    Functions\when('update_option')->alias(static function (string $key, mixed $value) use (&$options, $wpdb): bool {
        $options[$key] = $value;
        $GLOBALS['corex_recovery_lock_at_write'] = $wpdb->held;

        return true;
    });

    return $wpdb;
}

it('spec 124 FR-015: writes the login settings under their lock and lets it go', function () {
    $options = ['siteurl' => 'https://corex.test', 'corex_login_protection_settings' => ['enabled' => true]];
    $wpdb = corexRecoveryOptions($options);

    (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0), new LoginProtectionSettingsStore()))->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($GLOBALS['corex_recovery_lock_at_write'])->toStartWith('corex_login_settings_')
        ->and($wpdb->held)->toBeNull();
});

it('spec 124 FR-015: recovers even while another save holds the lock, and says that save may be overwritten', function () {
    $options = ['siteurl' => 'https://corex.test', 'corex_login_protection_settings' => [
        'hide_wp_login' => true, 'hide_admin' => true, 'limit_attempts' => true, 'enabled' => true, 'block_default_endpoints' => true,
    ]];
    $wpdb = corexRecoveryOptions($options);
    $wpdb->grants = false;
    $lockouts = new CorexTestLoginLockoutStore(2);
    $command = new SecurityResetLoginCommand($lockouts, new LoginProtectionSettingsStore());

    $result = $command->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));
    $after = $options['corex_login_protection_settings'];

    expect([$after['hide_wp_login'], $after['hide_admin'], $after['limit_attempts'], $after['enabled'], $after['block_default_endpoints']])
        ->toBe([false, false, false, false, false])
        ->and($result['released_lockouts'])->toBe(2)
        ->and($result['written_without_lock'])->toBeTrue()
        // Written without the lock, so none was taken and none is left held.
        ->and($GLOBALS['corex_recovery_lock_at_write'])->toBeNull()
        ->and($wpdb->held)->toBeNull()
        ->and($command->report($result))->toContain(
            'Another save of the login settings was in progress; it may have been overwritten. Check the settings once you are signed in.',
        );
});

it('spec 124 FR-015: says nothing about a concurrent save when it had the lock', function () {
    $options = ['siteurl' => 'https://corex.test', 'corex_login_protection_settings' => ['enabled' => true]];
    corexRecoveryOptions($options);
    $command = new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0), new LoginProtectionSettingsStore());

    $result = $command->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($result['written_without_lock'])->toBeFalse()
        ->and(implode("\n", $command->report($result)))->not->toContain('overwritten');
});

it('disables protected login settings and releases active lockouts without touching unrelated settings', function () {
    $options = [
        'siteurl' => 'https://corex.test',
        'corex_login_protection_settings' => [
            'enabled' => true,
            'custom_slug' => 'team-login',
            'block_default_endpoints' => true,
            'retain_days' => 30,
        ],
    ];
    corexRecoveryOptions($options);

    $store = new CorexTestLoginLockoutStore(3);
    $result = (new SecurityResetLoginCommand($store, new LoginProtectionSettingsStore()))->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($result['restored_login_url'])->toBe('https://corex.test/wp-login.php')
        ->and($result['released_lockouts'])->toBe(3)
        ->and($options['corex_login_protection_settings']['enabled'])->toBeFalse()
        ->and($options['corex_login_protection_settings']['block_default_endpoints'])->toBeFalse()
        // Unrelated settings stay as they were.
        ->and($options['corex_login_protection_settings']['retain_days'])->toBe(30)
        ->and($store->calledAt?->format(DATE_ATOM))->toBe('2026-07-07T12:00:00+00:00');
});

it('spec 124 scenario 7: turns all three switches off, in both stored shapes', function (array $stored) {
    $options = [
        'siteurl' => 'https://corex.test',
        'corex_login_protection_settings' => $stored + ['custom_slug' => 'team-login', 'threshold' => 7],
    ];
    corexRecoveryOptions($options);

    (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0), new LoginProtectionSettingsStore()))
        ->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));
    $after = $options['corex_login_protection_settings'];

    expect([$after['hide_wp_login'], $after['hide_admin'], $after['limit_attempts']])->toBe([false, false, false])
        ->and($after['enabled'])->toBeFalse()
        ->and($after['block_default_endpoints'])->toBeFalse()
        ->and($after['threshold'])->toBe(7);

    // What the store reads back is all three off, whichever release reads it. (The stub reads
    // the options as they were when it was made, so it is made again.)
    corexRecoveryOptions($options);
    Functions\when('sanitize_title')->returnArg();
    $settings = (new \Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore())->current();
    expect([$settings->hideWpLogin, $settings->hideAdmin, $settings->limitAttempts])->toBe([false, false, false]);
})->with([
    'stored by the previous release' => [['enabled' => true, 'block_default_endpoints' => true]],
    'stored by this release' => [['hide_wp_login' => true, 'hide_admin' => true, 'limit_attempts' => true, 'enabled' => true, 'block_default_endpoints' => true]],
    'one switch on' => [['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'enabled' => true, 'block_default_endpoints' => false]],
]);

it('resets the custom slug so re-enabling cannot walk back into the same lockout', function () {
    $options = [
        'siteurl' => 'https://corex.test',
        'corex_login_protection_settings' => ['enabled' => true, 'custom_slug' => 'team-login'],
    ];
    corexRecoveryOptions($options);

    (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0), new LoginProtectionSettingsStore()))
        ->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($options['corex_login_protection_settings']['custom_slug'])->toBe(LoginSlug::DEFAULT);
});

it('reports the default login URL without asking wp_login_url', function () {
    // The regression this exists for: the command used to report wp_login_url(), which the guard's
    // own filters rewrite to the custom slug — so recovery printed the address it had just
    // disabled, handing a locked-out owner a URL that 404s (DECISIONS #140). The old test mocked
    // wp_login_url() to return the right answer and so could never have caught it.
    $options = [
        'siteurl' => 'https://corex.test',
        'corex_login_protection_settings' => ['enabled' => true, 'custom_slug' => 'team-login'],
    ];
    corexRecoveryOptions($options);
    Functions\expect('wp_login_url')->never();

    $result = (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0), new LoginProtectionSettingsStore()))
        ->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($result['restored_login_url'])->toBe('https://corex.test/wp-login.php')
        ->and($result['restored_login_url'])->not->toContain('team-login');
});

it('does not double the slash when the site URL has a trailing one', function () {
    $options = [
        'siteurl' => 'https://corex.test/',
        'corex_login_protection_settings' => ['enabled' => true],
    ];
    corexRecoveryOptions($options);

    $result = (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0), new LoginProtectionSettingsStore()))
        ->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($result['restored_login_url'])->toBe('https://corex.test/wp-login.php');
});

final class CorexTestLoginLockoutStore implements LoginLockoutStore
{
    public ?DateTimeImmutable $calledAt = null;

    public function __construct(private readonly int $released)
    {
    }

    public function releaseActiveLockouts(DateTimeImmutable $now): int
    {
        $this->calledAt = $now;

        return $this->released;
    }
}
