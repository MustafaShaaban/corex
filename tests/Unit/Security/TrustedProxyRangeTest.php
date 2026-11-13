<?php

/**
 * The one rule for a trusted proxy line, shared by the login settings route and
 * `wp corex security trusted-proxies` (spec 124, FR-015; DECISIONS #629, amended 2026-11-13).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\TrustedProxyRange;

it('FR-015: stores an address and a range in one spelling, whatever spelling was typed', function (string $typed, string $stored) {
    expect(TrustedProxyRange::canonical($typed))->toBe($stored);
})->with([
    'IPv6 upper case'        => ['2001:DB8::1', '2001:db8::1'],
    'IPv6 written in full'   => ['2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1'],
    'IPv6 range'             => ['2001:0DB8::/032', '2001:db8::/32'],
    'IPv4 as it is'          => ['203.0.113.7', '203.0.113.7'],
    'IPv4 range'             => ['203.0.113.0/24', '203.0.113.0/24'],
    // Kept as the mapped IPv6 address it is: it is not the same as 10.0.0.1 (see ClientIpResolver).
    'IPv4-mapped IPv6'       => ['::FFFF:10.0.0.1', '::ffff:10.0.0.1'],
]);

it('has no spelling for a line it refuses', function (string $line) {
    expect(TrustedProxyRange::canonical($line))->toBeNull();
})->with(['proxy.example.test', '10.0.0.0/33', '0.0.0.0/0', '::/0', '10.0.0.0/']);

it('FR-015: refuses more lines than it keeps, here for the route and the command alike', function () {
    expect(TrustedProxyRange::tooMany(TrustedProxyRange::MAX_LINES))->toBeFalse()
        ->and(TrustedProxyRange::tooMany(TrustedProxyRange::MAX_LINES + 1))->toBeTrue()
        ->and(TrustedProxyRange::MAX_LINES)->toBe(100);
});

it('FR-015: says a /0 range trusts every address, apart from a malformed line', function () {
    expect(TrustedProxyRange::rejectionReason('0.0.0.0/0'))->toBe(TrustedProxyRange::REASON_TRUSTS_EVERY_ADDRESS)
        ->and(TrustedProxyRange::rejectionReason('::/0'))->toBe(TrustedProxyRange::REASON_TRUSTS_EVERY_ADDRESS)
        ->and(TrustedProxyRange::rejectionReason('10.0.0.0/33'))->toBe(TrustedProxyRange::REASON_FORMAT)
        ->and(TrustedProxyRange::rejectionReason('10.0.0.0/8'))->toBeNull();
});

it('says whether a stored list holds a line that is not an address or range, which Hardening reports', function () {
    expect(TrustedProxyRange::holdsMalformed(['203.0.113.0/24', '10.0.0.0/']))->toBeTrue()
        ->and(TrustedProxyRange::holdsMalformed(['10.0.0.0/x']))->toBeTrue()
        ->and(TrustedProxyRange::holdsMalformed(['203.0.113.0/24', '0.0.0.0/0']))->toBeFalse()
        ->and(TrustedProxyRange::holdsMalformed([]))->toBeFalse();
});

it('says whether a stored list trusts every address, which Hardening reports', function () {
    expect(TrustedProxyRange::trustsEveryAddress(['203.0.113.0/24', '0.0.0.0/0']))->toBeTrue()
        ->and(TrustedProxyRange::trustsEveryAddress(['203.0.113.0/24', '2001:db8::/32']))->toBeFalse()
        ->and(TrustedProxyRange::trustsEveryAddress([]))->toBeFalse();
});
