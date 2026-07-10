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

it('disables protected login settings and releases active lockouts without touching unrelated settings', function () {
    $options = [
        'corex_login_protection_settings' => [
            'enabled' => true,
            'custom_slug' => 'team-login',
            'block_default_endpoints' => true,
            'retain_days' => 30,
        ],
    ];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $options[$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, mixed $value) use (&$options): bool {
        $options[$key] = $value;

        return true;
    });
    Functions\when('wp_login_url')->justReturn('https://corex.test/wp-login.php');

    $store = new CorexTestLoginLockoutStore(3);
    $result = (new SecurityResetLoginCommand($store))->restore(new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($result['restored_login_url'])->toBe('https://corex.test/wp-login.php')
        ->and($result['released_lockouts'])->toBe(3)
        ->and($options['corex_login_protection_settings']['enabled'])->toBeFalse()
        ->and($options['corex_login_protection_settings']['block_default_endpoints'])->toBeFalse()
        ->and($options['corex_login_protection_settings']['custom_slug'])->toBe('team-login')
        ->and($store->calledAt?->format(DATE_ATOM))->toBe('2026-07-07T12:00:00+00:00');
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
