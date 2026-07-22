<?php

/**
 * Unit tests for the token replay guard (spec 071 US1: FR-003 replay, §4.5).
 *
 * A consumed token must not be accepted twice, the state must be bounded by a TTL rather
 * than a table, and the plaintext token must never be stored.
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Captcha\TokenReplayGuard;

beforeEach(function () {
    Functions\when('wp_salt')->justReturn('unit-test-salt');
});

/** A shared transient store both mocked functions read and write, so state survives across calls. */
function transientStore(): ArrayObject
{
    $store = new ArrayObject();
    Functions\when('get_transient')->alias(fn (string $k) => $store->offsetExists($k) ? $store[$k] : false);
    Functions\when('set_transient')->alias(function (string $k, $v, $ttl) use ($store): bool {
        $store[$k] = $v;
        return true;
    });

    return $store;
}

it('permits a token the first time and refuses the same token afterwards', function () {
    transientStore();
    $guard = new TokenReplayGuard();

    expect($guard->consume('a-real-token'))->toBeTrue()   // first use: fresh
        ->and($guard->consume('a-real-token'))->toBeFalse(); // second use: replay
});

it('treats distinct tokens independently', function () {
    transientStore();
    $guard = new TokenReplayGuard();

    expect($guard->consume('token-one'))->toBeTrue()
        ->and($guard->consume('token-two'))->toBeTrue();
});

it('stores a fingerprint, never the plaintext token', function () {
    $captured = [];
    Functions\when('get_transient')->justReturn(false);
    Functions\when('set_transient')->alias(function (string $k, $v, $ttl) use (&$captured): bool {
        $captured['key'] = $k;
        $captured['ttl'] = $ttl;
        return true;
    });

    (new TokenReplayGuard())->consume('super-secret-token-value');

    expect($captured['key'])->not->toContain('super-secret-token-value')
        ->and($captured['ttl'])->toBeGreaterThan(0)   // bounded — TTL is the cleanup
        ->and($captured['ttl'])->toBeLessThanOrEqual(600);
});
