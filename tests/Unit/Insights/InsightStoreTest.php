<?php

/**
 * Unit tests for the pure insight cache/history transformer (spec 037: FR-005). It transforms the
 * decoded option state; the WP option read/write lives in the controller boundary.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\InsightStore;

function result(string $provider, int $score, int $at): InsightResult
{
    return new InsightResult($provider, ucfirst($provider), $score, 'summary', [], [], $at);
}

it('stores the latest result for a provider and reads it back', function () {
    $store = new InsightStore();

    $state = $store->put([], result('psi', 80, 100));

    expect($store->latest($state, 'psi'))->not->toBeNull()
        ->and($store->latest($state, 'psi')['score'])->toBe(80)
        ->and($store->latest($state, 'cloudflare'))->toBeNull();
});

it('keeps a newest-first, bounded history', function () {
    $store = new InsightStore();
    $state = [];

    foreach ([10, 20, 30, 40, 50, 60] as $i => $score) {
        $state = $store->put($state, result('psi', $score, $i), 3);
    }

    $history = $store->history($state, 'psi');

    expect($history)->toHaveCount(3)
        ->and($history[0]['score'])->toBe(60)   // newest first
        ->and($history[2]['score'])->toBe(40)   // oldest kept within the limit of 3
        ->and($store->latest($state, 'psi')['score'])->toBe(60);
});

it('maps every provider to its latest for the dashboard', function () {
    $store = new InsightStore();
    $state = $store->put($store->put([], result('psi', 90, 1)), result('cloudflare', 70, 2));

    $all = $store->all($state);

    expect($all)->toHaveKeys(['psi', 'cloudflare'])
        ->and($all['psi']['grade'])->toBe('A')
        ->and($all['cloudflare']['grade'])->toBe('C');
});
