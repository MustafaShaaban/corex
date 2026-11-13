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
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSettingsBusy;
use Corex\Tests\Support\NamedLockWpdb;

/**
 * @param array<string,mixed> $options
 */
function trustedProxyOptions(array &$options): NamedLockWpdb
{
    $wpdb = new NamedLockWpdb();
    $GLOBALS['wpdb'] = $wpdb;
    $GLOBALS['corex_trusted_proxy_lock_at_write'] = null;
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $options[$key] ?? $default);
    Functions\when('wp_set_option_autoload')->justReturn(true);
    Functions\when('wp_cache_delete')->justReturn(true);
    Functions\when('update_option')->alias(static function (string $key, mixed $value) use (&$options, $wpdb): bool {
        $options[$key] = $value;
        $GLOBALS['corex_trusted_proxy_lock_at_write'] = $wpdb->held;

        return true;
    });

    return $wpdb;
}

it('trusts the proxies it is given and leaves the rest of login protection alone', function () {
    $options = ['corex_login_protection_settings' => ['enabled' => true, 'custom_slug' => 'team-login']];
    trustedProxyOptions($options);

    $refused = (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust(['127.0.0.1', '::1', '10.0.0.0/8', '2001:db8::/32']);

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

    (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust(['127.0.0.1']);

    expect($options['corex_login_protection_settings'])->toBe([
        'trusted_proxy_mode' => true,
        'trusted_proxy_ranges' => ['127.0.0.1'],
    ]);
});

it('refuses the whole list when one entry is not an address or a range, and saves nothing', function (string $entry) {
    $options = ['corex_login_protection_settings' => ['trusted_proxy_mode' => true, 'trusted_proxy_ranges' => ['127.0.0.1']]];
    trustedProxyOptions($options);

    $refused = (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust(['10.0.0.1', $entry]);

    expect($refused)->toBe([$entry])
        ->and($options['corex_login_protection_settings']['trusted_proxy_ranges'])->toBe(['127.0.0.1']);
})->with([
    'a hostname' => ['proxy.example.com'],
    'a prefix longer than an IPv4 address' => ['10.0.0.0/33'],
    'a prefix longer than an IPv6 address' => ['2001:db8::/129'],
    'a prefix that is not a number' => ['10.0.0.0/eight'],
    'a range with no prefix' => ['10.0.0.0/'],
]);

it('refuses a range that trusts every address, and saves nothing (spec 124, FR-015)', function (string $entry) {
    $options = ['corex_login_protection_settings' => ['trusted_proxy_mode' => true, 'trusted_proxy_ranges' => ['127.0.0.1']]];
    trustedProxyOptions($options);

    $command = new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore());
    $refused = $command->trust(['10.0.0.1', $entry]);

    expect($refused)->toBe([$entry])
        ->and($options['corex_login_protection_settings']['trusted_proxy_ranges'])->toBe(['127.0.0.1'])
        ->and($command->refusal($refused))->toContain($entry)->toContain('every address');
})->with([
    'IPv4 /0' => ['0.0.0.0/0'],
    'IPv6 /0' => ['::/0'],
    'any IPv4 network /0' => ['10.0.0.0/0'],
]);

it('spec 124 FR-015: stores each address in one spelling, as the screen does', function () {
    $options = [];
    trustedProxyOptions($options);

    (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust(['2001:DB8::1', '2001:0db8:0000:0000:0000:0000:0000:0000/032']);

    expect($options['corex_login_protection_settings']['trusted_proxy_ranges'])->toBe(['2001:db8::1', '2001:db8::/32']);
});

it('spec 124 FR-015: refuses more than 100 proxies, as the screen does, and saves nothing', function () {
    $options = ['corex_login_protection_settings' => ['trusted_proxy_ranges' => ['127.0.0.1']]];
    trustedProxyOptions($options);
    $ranges = array_map(static fn (int $i): string => '10.0.' . intdiv($i, 256) . '.' . ($i % 256), range(1, 101));

    $command = new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore());

    expect($command->trust($ranges))->not->toBe([])
        ->and($options['corex_login_protection_settings']['trusted_proxy_ranges'])->toBe(['127.0.0.1'])
        ->and($command->refusal($ranges))->toBe('Give at most 100 proxies. Nothing was changed.')
        ->and($command->trust(array_slice($ranges, 0, 100)))->toBe([]);
});

it('spec 124 FR-015: writes under the login settings lock and lets it go', function () {
    $options = [];
    $wpdb = trustedProxyOptions($options);

    (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust(['127.0.0.1']);

    expect($GLOBALS['corex_trusted_proxy_lock_at_write'])->toStartWith('corex_login_settings_')
        ->and($wpdb->held)->toBeNull();
});

it('spec 124 FR-015: writes nothing while another save holds the lock', function () {
    $options = ['corex_login_protection_settings' => ['trusted_proxy_ranges' => ['127.0.0.1']]];
    $wpdb = trustedProxyOptions($options);
    $wpdb->grants = false;

    expect(fn () => (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust(['10.0.0.1']))->toThrow(LoginSettingsBusy::class);

    expect($options['corex_login_protection_settings']['trusted_proxy_ranges'])->toBe(['127.0.0.1']);
});

it('says which entries are not an address or a range', function () {
    expect((new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->refusal(['proxy.example.com', '10.0.0.0/33']))
        ->toBe('Not an address or a range: proxy.example.com, 10.0.0.0/33. Nothing was changed.');
});

it('trusts nobody once the list is cleared', function () {
    $options = ['corex_login_protection_settings' => [
        'custom_slug' => 'team-login',
        'trusted_proxy_mode' => true,
        'trusted_proxy_ranges' => ['127.0.0.1'],
    ]];
    trustedProxyOptions($options);

    (new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trust([]);

    expect($options['corex_login_protection_settings'])->toBe([
        'custom_slug' => 'team-login',
        'trusted_proxy_mode' => false,
        'trusted_proxy_ranges' => [],
    ]);
});

it('reports the proxies trusted now, and none while the mode is off', function (array $stored, array $trusted) {
    $options = ['corex_login_protection_settings' => $stored];
    trustedProxyOptions($options);

    expect((new SecurityTrustedProxiesCommand(new LoginProtectionSettingsStore()))->trusted())->toBe($trusted);
})->with([
    'a saved list' => [['trusted_proxy_mode' => true, 'trusted_proxy_ranges' => ['127.0.0.1', '::1']], ['127.0.0.1', '::1']],
    'a list left behind with the mode off' => [['trusted_proxy_mode' => false, 'trusted_proxy_ranges' => ['127.0.0.1']], []],
    'nothing saved' => [[], []],
]);
