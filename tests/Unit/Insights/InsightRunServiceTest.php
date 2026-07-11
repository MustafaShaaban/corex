<?php

/**
 * Unit tests for the Insights run/history/recommendation service (spec 068: T187). No WordPress.
 * Contract: run real providers, record bounded history, aggregate only real recommendations,
 * and reject an unknown provider without fabricating a result.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\InsightRegistry;
use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\InsightRunService;
use Corex\Config\Insights\InsightStore;
use Corex\Config\Insights\InsightProvider;

/**
 * A real in-memory provider test double (no mock framework) returning a fixed result.
 */
function insightRunFakeProvider(string $id, string $label, InsightResult $result): InsightProvider
{
    return new class ($id, $label, $result) implements InsightProvider {
        public function __construct(
            private readonly string $id,
            private readonly string $label,
            private readonly InsightResult $result,
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return $this->label;
        }

        public function run(string $url): InsightResult
        {
            return $this->result;
        }
    };
}

function insightRunSvc(): InsightRunService
{
    $registry = new InsightRegistry();
    $registry->register(insightRunFakeProvider('performance', 'Performance', new InsightResult('performance', 'Performance', 50, 'Slow', [], ['Compress images', 'Enable caching'], 111)));
    $registry->register(insightRunFakeProvider('readiness', 'Readiness', new InsightResult('readiness', 'Readiness', 90, 'Good', [], [], 222)));

    return new InsightRunService($registry, new InsightStore());
}

it('runs a provider, records it, and returns the updated state and result', function () {
    $out = insightRunSvc()->run('performance', 'https://example.test/', []);

    expect($out)->not->toBeNull()
        ->and($out['result']['provider'])->toBe('performance')
        ->and($out['result']['grade'])->toBe('D')
        ->and($out['state']['performance']['latest']['score'])->toBe(50)
        ->and($out['state']['performance']['history'])->toHaveCount(1);
});

it('returns null for an unknown provider instead of fabricating a result', function () {
    expect(insightRunSvc()->run('ghost', 'https://example.test/', []))->toBeNull();
});

it('aggregates recommendations only from providers whose latest result has them', function () {
    $service = insightRunSvc();

    // Only performance has recommendations; readiness ran clean.
    $state = $service->run('performance', 'https://example.test/', [])['state'];
    $state = $service->run('readiness', 'https://example.test/', $state)['state'];

    $recommendations = $service->recommendations($state);

    expect($recommendations)->toHaveCount(1)
        ->and($recommendations[0]['provider'])->toBe('performance')
        ->and($recommendations[0]['grade'])->toBe('D')
        ->and($recommendations[0]['recommendations'])->toBe(['Compress images', 'Enable caching']);
});

it('keeps newest-first bounded history across repeated runs', function () {
    $service = insightRunSvc();

    $state = [];
    for ($i = 0; $i < 7; $i++) {
        $state = $service->run('performance', 'https://example.test/', $state)['state'];
    }

    // Default history bound is 5.
    expect($service->history($state, 'performance'))->toHaveCount(5)
        ->and($service->latest($state, 'performance')['provider'])->toBe('performance');
});
