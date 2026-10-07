<?php

/**
 * Unit tests for the two answers to "who is making this request" (#247).
 *
 * Forms keyed their rate limit on `REMOTE_ADDR`, which behind a proxy is the proxy: every visitor
 * was one client. The contract lives in corex-core so corex-forms can ask without depending on
 * corex-config, which holds the trusted-proxy setting and supplies the answer that reads it.
 *
 * @package Corex\Tests\Unit\Http
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\TrustedProxyClientAddress;
use Corex\Http\RemoteAddress;

/**
 * @param list<string> $trustedProxies
 */
function proxyAwareAddress(array $trustedProxies): TrustedProxyClientAddress
{
    return new TrustedProxyClientAddress(new ClientIpResolver(new LoginProtectionSettings(
        enabled: false,
        customSlug: 'team-login',
        blockDefaultEndpoints: true,
        threshold: 5,
        windowSeconds: 300,
        lockoutSeconds: 900,
        trustedProxyMode: $trustedProxies !== [],
        trustedProxyRanges: $trustedProxies,
        retainDays: 30,
        successfulLoginLogging: true,
    )));
}

beforeEach(function () {
    $this->server = $_SERVER;
});

afterEach(function () {
    $_SERVER = $this->server;
});

it('answers with the connection’s address when nothing says which proxies to trust', function (array $server, string $address) {
    $_SERVER = $server;

    expect((new RemoteAddress())->current())->toBe($address);
})->with([
    'a direct visitor' => [['REMOTE_ADDR' => '203.0.113.9'], '203.0.113.9'],
    'an IPv6 visitor' => [['REMOTE_ADDR' => '2001:db8::10'], '2001:db8::10'],
    'a forwarded header nobody vouches for' => [
        ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'],
        '10.0.0.1',
    ],
    'no connection, as under WP-CLI' => [[], ''],
    'something that is not an address' => [['REMOTE_ADDR' => 'not-an-address'], ''],
]);

it('answers with the visitor a trusted proxy forwarded', function (array $server, string $address) {
    $_SERVER = $server;

    expect(proxyAwareAddress(['10.0.0.1'])->current())->toBe($address);
})->with([
    'one visitor behind the proxy' => [
        ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'],
        '198.51.100.7',
    ],
    'another visitor behind the same proxy' => [
        ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.8'],
        '198.51.100.8',
    ],
    'a visitor who wrote a forwarded header of their own' => [
        ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.1, 198.51.100.7'],
        '198.51.100.7',
    ],
    'a connection that is not from the proxy' => [
        ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'],
        '203.0.113.9',
    ],
    'no connection, as under WP-CLI' => [[], ''],
]);

it('answers with the connection’s address while no proxy is trusted', function () {
    $_SERVER = ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'];

    expect(proxyAwareAddress([])->current())->toBe('10.0.0.1');
});
