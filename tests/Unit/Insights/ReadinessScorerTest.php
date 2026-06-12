<?php

/**
 * Unit tests for the pure agent-readiness scorer (spec 037: FR-003). Native signal booleans
 * become a 0–100 score, pass/fail metrics, and a recommendation per failing signal.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\ReadinessScorer;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('scores all signals passing as 100 with no recommendations', function () {
    $out = (new ReadinessScorer())->score([
        'https'         => true,
        'llms_txt'      => true,
        'sitemap'       => true,
        'robots_agents' => true,
        'mcp_abilities' => true,
    ]);

    expect($out['score'])->toBe(100)
        ->and($out['recommendations'])->toBe([])
        ->and($out['metrics'])->toHaveCount(5);
});

it('deducts per failing signal and recommends a fix for each', function () {
    $out = (new ReadinessScorer())->score([
        'https'         => true,
        'llms_txt'      => false,
        'sitemap'       => false,
        'robots_agents' => true,
        'mcp_abilities' => true,
    ]);

    expect($out['score'])->toBe(60) // 3 of 5 pass
        ->and($out['recommendations'])->toHaveCount(2);
});

it('treats missing signals as failing (defensive default)', function () {
    $out = (new ReadinessScorer())->score([]);

    expect($out['score'])->toBe(0)
        ->and($out['recommendations'])->toHaveCount(5);
});
