<?php

/**
 * Unit tests for `wp corex security trusted-proxies` (#247).
 *
 * The list of proxies a site trusts decides who a login lockout and a form's rate limit count as
 * the client. It had a setting and no way to set it short of posting to the REST route by hand.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Commands\SecurityTrustedProxiesCommand;

/**
 * @param array<string,mixed> $options
 */
function trustedProxyOptions(array &$options): void
{
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $options[$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, mixed $value) use (&$options): bool {
        $options[$key] = $value;

        return true;
    });
}

it('trusts the proxies it is given and leaves the rest of login protection alone', function () {
    $options = ['corex_login_protection_settings' => ['enabled' => true, 'custom_slug' => 'team-login']];
    trustedProxyOptions($options);

    $refused = (new SecurityTrustedProxiesCommand())->trust(['127.0.0.1', '::1', '10.0.0.0/8', '2001:db8::/32']);

    expect($refused)->toBe([])
        ->and($options['corex_login_protection_settings'])->toBe([
            'enabled' => true,
            'custom_slug' => 'team-login',
            'trusted_proxy_mode' => true,
            'trusted_proxy_ranges' => ['127.0.0.1', '::1', '10.0.0.0/8', '2001:db8::/32'],
        ]);
});

it('trusts a proxy on a site that has never saved login protection', function () {
    $options = [];
    trustedProxyOptions($options);

    (new SecurityTrustedProxiesCommand())->trust(['127.0.0.1']);

    expect($options['corex_login_protection_settings'])->toBe([
        'trusted_proxy_mode' => true,
        'trusted_proxy_ranges' => ['127.0.0.1'],
    ]);
});

it('refuses the whole list when one entry is not an address or a range, and saves nothing', function (string $entry) {
    $options = ['corex_login_protection_settings' => ['trusted_proxy_mode' => true, 'trusted_proxy_ranges' => ['127.0.0.1']]];
    trustedProxyOptions($options);

    $refused = (new SecurityTrustedProxiesCommand())->trust(['10.0.0.1', $entry]);

    expect($refused)->toBe([$entry])
        ->and($options['corex_login_protection_settings']['trusted_proxy_ranges'])->toBe(['127.0.0.1']);
})->with([
    'a hostname' => ['proxy.example.com'],
    'a prefix longer than an IPv4 address' => ['10.0.0.0/33'],
    'a prefix longer than an IPv6 address' => ['2001:db8::/129'],
    'a prefix that is not a number' => ['10.0.0.0/eight'],
    'a range with no prefix' => ['10.0.0.0/'],
]);

it('trusts nobody once the list is cleared', function () {
    $options = ['corex_login_protection_settings' => [
        'custom_slug' => 'team-login',
        'trusted_proxy_mode' => true,
        'trusted_proxy_ranges' => ['127.0.0.1'],
    ]];
    trustedProxyOptions($options);

    (new SecurityTrustedProxiesCommand())->trust([]);

    expect($options['corex_login_protection_settings'])->toBe([
        'custom_slug' => 'team-login',
        'trusted_proxy_mode' => false,
        'trusted_proxy_ranges' => [],
    ]);
});

it('reports the proxies trusted now, and none while the mode is off', function (array $stored, array $trusted) {
    $options = ['corex_login_protection_settings' => $stored];
    trustedProxyOptions($options);

    expect((new SecurityTrustedProxiesCommand())->trusted())->toBe($trusted);
})->with([
    'a saved list' => [['trusted_proxy_mode' => true, 'trusted_proxy_ranges' => ['127.0.0.1', '::1']], ['127.0.0.1', '::1']],
    'a list left behind with the mode off' => [['trusted_proxy_mode' => false, 'trusted_proxy_ranges' => ['127.0.0.1']], []],
    'nothing saved' => [[], []],
]);
