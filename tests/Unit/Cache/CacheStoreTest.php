<?php

/**
 * The cache contract (spec 078, T010/T011 / FR-006/009).
 *
 * Exercised against ArrayCacheStore, whose clock is injectable — so "this expires after a minute" is
 * asserted as a fact rather than waited out with a sleep.
 *
 * @package Corex\Tests\Unit\Cache
 */

declare(strict_types=1);

use Corex\Cache\ArrayCacheStore;

beforeEach(function () {
    $this->now   = 1_000_000;
    $this->store = new ArrayCacheStore(fn (): int => $this->now);
});

it('returns what was put', function () {
    $this->store->put('forms', 'counts', ['a' => 1]);

    expect($this->store->get('forms', 'counts'))->toBe(['a' => 1])
        ->and($this->store->has('forms', 'counts'))->toBeTrue();
});

it('returns the default on a miss rather than null-by-accident', function () {
    // The distinction matters: a cached `null` and an absent key are different, and a store that
    // conflates them makes `remember()` recompute forever.
    expect($this->store->get('forms', 'absent', 'fallback'))->toBe('fallback');

    $this->store->put('forms', 'explicit-null', null);

    expect($this->store->get('forms', 'explicit-null', 'fallback'))->toBeNull()
        ->and($this->store->has('forms', 'explicit-null'))->toBeTrue();
});

it('expires a value when its time is up, and not a second before', function () {
    $this->store->put('forms', 'counts', 'value', 60);

    $this->now += 59;
    expect($this->store->get('forms', 'counts'))->toBe('value');

    $this->now += 1;
    expect($this->store->get('forms', 'counts'))->toBeNull();
});

it('keeps a value with no ttl for as long as the store lives', function () {
    $this->store->put('forms', 'forever', 'value');

    $this->now += 86_400 * 365;

    expect($this->store->get('forms', 'forever'))->toBe('value');
});

it('forgets one key without touching its neighbours', function () {
    $this->store->put('forms', 'a', 1);
    $this->store->put('forms', 'b', 2);

    $this->store->forget('forms', 'a');

    expect($this->store->has('forms', 'a'))->toBeFalse()
        ->and($this->store->get('forms', 'b'))->toBe(2);
});

it('invalidates a whole namespace at once, and leaves other namespaces alone', function () {
    $this->store->put('forms', 'a', 1);
    $this->store->put('forms', 'b', 2);
    $this->store->put('data', 'c', 3);

    $this->store->forgetNamespace('forms');

    expect($this->store->has('forms', 'a'))->toBeFalse()
        ->and($this->store->has('forms', 'b'))->toBeFalse()
        ->and($this->store->get('data', 'c'))->toBe(3);
});

it('computes once on a miss and reads the cache afterwards', function () {
    $calls = 0;
    $compute = function () use (&$calls): string {
        $calls++;

        return 'computed';
    };

    expect($this->store->remember('forms', 'k', 60, $compute))->toBe('computed')
        ->and($this->store->remember('forms', 'k', 60, $compute))->toBe('computed')
        ->and($calls)->toBe(1);
});

it('recomputes after the remembered value expires', function () {
    $calls = 0;
    $compute = function () use (&$calls): int {
        return ++$calls;
    };

    expect($this->store->remember('forms', 'k', 60, $compute))->toBe(1);

    $this->now += 61;

    expect($this->store->remember('forms', 'k', 60, $compute))->toBe(2);
});

it('says whether it survives the request', function () {
    // Reported rather than assumed: it changes what the cache is worth, and the status screen has
    // to be able to tell an operator which kind their site has.
    expect($this->store->isPersistent())->toBeFalse()
        ->and($this->store->describe())->toContain('request');
});

it('treats a namespace as opaque, so two namespaces cannot collide', function () {
    $this->store->put('forms', 'same-key', 'forms value');
    $this->store->put('data', 'same-key', 'data value');

    expect($this->store->get('forms', 'same-key'))->toBe('forms value')
        ->and($this->store->get('data', 'same-key'))->toBe('data value');
});
