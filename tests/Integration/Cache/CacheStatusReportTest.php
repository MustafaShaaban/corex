<?php

/**
 * Every layer reported from a real check (spec 078, T026 / FR-017/021, SC-005).
 *
 * @package Corex\Tests\Integration\Cache
 */

declare(strict_types=1);

use Corex\Cache\Status\CacheLayerState;
use Corex\Cache\Status\CacheStatusReport;
use Corex\Cache\WordPressCacheStore;

beforeEach(function () {
    $this->report = new CacheStatusReport(new WordPressCacheStore());
    $this->layers = [];

    foreach ($this->report->layers() as $layer) {
        $this->layers[$layer->key] = $layer;
    }
});

it('reports all seven layers', function () {
    expect(array_keys($this->layers))->toBe([
        'browser', 'opcache', 'request', 'object', 'application', 'page', 'cdn',
    ]);
});

it('never reports an object cache as active unless WordPress is using one', function () {
    // The trap FR-017 names: a running Redis container is a fact about the server, and whether
    // WordPress is using it is the fact that affects this site.
    $inUse = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();

    if (! $inUse) {
        expect($this->layers['object']->state)->not->toBe(CacheLayerState::Active);
    }
});

it('says a missing object cache is normal rather than a problem', function () {
    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
        expect(true)->toBeTrue();

        return;
    }

    expect($this->layers['object']->state->isConcerning())->toBeFalse()
        ->and($this->layers['object']->detail)->toContain('not required');
});

it('reports OPcache as unknown rather than off when it cannot look', function () {
    $layer = $this->layers['opcache'];

    if (! function_exists('opcache_get_status')) {
        expect($layer->state)->toBe(CacheLayerState::Unknown)
            ->and($layer->detail)->toContain('hosting setting');

        return;
    }

    // When it can look, the answer must be a real one rather than unknown.
    expect($layer->state)->not->toBe(CacheLayerState::Error);
});

it('says plainly that CoreX cannot clear a visitor browser cache', function () {
    // The layer people mean when they say "I cleared the cache and it is still old".
    expect($this->layers['browser']->manageable)->toBeFalse()
        ->and($this->layers['browser']->detail)->toContain('No website can empty');
});

it('offers no action for a layer with nothing behind it', function () {
    foreach (['page', 'cdn'] as $key) {
        $layer = $this->layers[$key];

        if ($layer->state === CacheLayerState::NotDetected) {
            expect($layer->manageable)->toBeFalse($key)
                ->and($layer->provider)->toBe('', $key);
        }
    }
});

it('will not reuse a credential granted for something else', function () {
    // FR-023, stated in the copy an operator reads rather than only in the spec.
    expect($this->layers['cdn']->detail)->toContain('will not reuse a credential');
});

it('gives every layer a purpose, a state and a check time', function () {
    foreach ($this->report->layers() as $layer) {
        expect($layer->name)->not->toBe('')
            ->and($layer->purpose)->not->toBe('', $layer->key)
            ->and($layer->checkedAt)->toMatch('/^\d{4}-\d{2}-\d{2}T/', $layer->key);
    }
});

it('exposes no secret', function () {
    $flat = strtolower((string) wp_json_encode(array_map(
        static fn ($layer): array => $layer->toArray(),
        $this->report->layers(),
    )));

    foreach (['password', 'secret', 'token', 'salt', 'api_key'] as $forbidden) {
        expect($flat)->not->toContain($forbidden);
    }
});
