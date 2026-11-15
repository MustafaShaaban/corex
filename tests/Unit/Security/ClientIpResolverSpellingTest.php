<?php

/**
 * A trusted proxy address is compared as the address it is, not as the text it was written in
 * (spec 124, FR-015; DECISIONS #629, amended 2026-11-13). Lists stored before CoreX wrote one
 * spelling may hold `2001:DB8::1` or the address written in full.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginSwitches;

function resolverTrusting(array $ranges): ClientIpResolver
{
    return new ClientIpResolver(LoginProtectionSettings::of(
        new LoginSwitches(false, false, true),
        trustedProxyMode: true,
        trustedProxyRanges: $ranges,
    ));
}

it('FR-015: trusts a proxy stored in any spelling of its IPv6 address', function (string $stored) {
    $resolved = resolverTrusting([$stored])->resolve([
        'REMOTE_ADDR'          => '2001:db8::1',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
    ]);

    expect($resolved)->toBe('198.51.100.20');
})->with([
    'upper case'     => ['2001:DB8::1'],
    'written in full' => ['2001:0db8:0000:0000:0000:0000:0000:0001'],
    'canonical'      => ['2001:db8::1'],
]);

it('FR-015: trusts nobody for a stored line whose prefix is not a number for the address', function (string $stored) {
    // The forwarded hop is IPv6, so no IPv4 range can vouch for it: trusting the peer answers the
    // hop, not trusting it answers the peer.
    $resolved = resolverTrusting([$stored])->resolve([
        'REMOTE_ADDR'          => '203.0.113.9',
        'HTTP_X_FORWARDED_FOR' => '2001:db8::20',
    ]);

    // Read as prefix 0, each of these trusted every IPv4 peer.
    expect($resolved)->toBe('203.0.113.9');
})->with(['10.0.0.0/', '10.0.0.0/x', '10.0.0.0/-1', '10.0.0.0/33', '10.0.0.0/8/8', 'proxy.example.test']);

it('FR-015: still honours a well-formed /0 stored before the rule, as decided', function (string $stored, string $remote, string $hop) {
    $resolved = resolverTrusting([$stored])->resolve([
        'REMOTE_ADDR'          => $remote,
        'HTTP_X_FORWARDED_FOR' => $hop,
    ]);

    expect($resolved)->toBe($hop);
})->with([
    'IPv4' => ['0.0.0.0/0', '203.0.113.9', '2001:db8::20'],
    'IPv6' => ['::/0', '2001:db8::9', '198.51.100.20'],
]);

it('still trusts the well-formed lines of a list that holds a malformed one', function () {
    $resolved = resolverTrusting(['10.0.0.0/x', '203.0.113.0/24'])->resolve([
        'REMOTE_ADDR'          => '203.0.113.9',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
    ]);

    expect($resolved)->toBe('198.51.100.20');
});

it('does not take an IPv4-mapped IPv6 address for the IPv4 address it carries, either way', function (string $stored, string $remote) {
    $resolved = resolverTrusting([$stored])->resolve([
        'REMOTE_ADDR'          => $remote,
        'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
    ]);

    // The server reports one spelling; the owner lists the one it reports.
    expect($resolved)->toBe($remote);
})->with([
    'mapped stored, IPv4 connects' => ['::ffff:10.0.0.1', '10.0.0.1'],
    'IPv4 stored, mapped connects' => ['10.0.0.1', '::ffff:10.0.0.1'],
]);
