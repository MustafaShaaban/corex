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
use Corex\Config\Security\LoginProtection\LoginSlug;

function corexRecoveryOptions(array &$options): void
{
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $options[$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, mixed $value) use (&$options): bool {
        $options[$key] = $value;

        return true;
    });
}

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
    $result = (new SecurityResetLoginCommand($store))->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($result['restored_login_url'])->toBe('https://corex.test/wp-login.php')
        ->and($result['released_lockouts'])->toBe(3)
        ->and($options['corex_login_protection_settings']['enabled'])->toBeFalse()
        ->and($options['corex_login_protection_settings']['block_default_endpoints'])->toBeFalse()
        // Unrelated settings stay as they were.
        ->and($options['corex_login_protection_settings']['retain_days'])->toBe(30)
        ->and($store->calledAt?->format(DATE_ATOM))->toBe('2026-07-07T12:00:00+00:00');
});

it('resets the custom slug so re-enabling cannot walk back into the same lockout', function () {
    $options = [
        'siteurl' => 'https://corex.test',
        'corex_login_protection_settings' => ['enabled' => true, 'custom_slug' => 'team-login'],
    ];
    corexRecoveryOptions($options);

    (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0)))
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

    $result = (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0)))
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

    $result = (new SecurityResetLoginCommand(new CorexTestLoginLockoutStore(0)))
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
