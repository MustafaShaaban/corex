<?php

/**
 * Unit tests for the pure PageSpeed Insights normaliser (spec 037: FR-003). A sample Lighthouse
 * payload becomes a scored result with Core Web Vitals + the top opportunities; a malformed
 * payload degrades to a graceful recommended result (FR-004).
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\Normalizers\PsiNormalizer;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function psiPayload(): array
{
    return [
        'lighthouseResult' => [
            'categories' => ['performance' => ['score' => 0.92]],
            'audits'     => [
                'largest-contentful-paint' => ['title' => 'Largest Contentful Paint', 'displayValue' => '1.2 s', 'score' => 0.9],
                'cumulative-layout-shift'  => ['title' => 'Cumulative Layout Shift', 'displayValue' => '0.05', 'score' => 1],
                'interaction-to-next-paint' => ['title' => 'Interaction to Next Paint', 'displayValue' => '120 ms', 'score' => 0.95],
                'first-contentful-paint'   => ['title' => 'First Contentful Paint', 'displayValue' => '0.9 s', 'score' => 1],
                'total-blocking-time'      => ['title' => 'Total Blocking Time', 'displayValue' => '150 ms', 'score' => 0.8],
                'unused-javascript'        => ['title' => 'Reduce unused JavaScript', 'score' => 0.3, 'details' => ['overallSavingsMs' => 800]],
                'render-blocking-resources' => ['title' => 'Eliminate render-blocking resources', 'score' => 0.5, 'details' => ['overallSavingsMs' => 400]],
            ],
        ],
    ];
}

it('normalises a PSI payload into a scored result with CWV metrics', function () {
    $result = (new PsiNormalizer())->normalize(psiPayload());

    expect($result)->toBeInstanceOf(InsightResult::class)
        ->and($result->providerId)->toBe('performance')
        ->and($result->score)->toBe(92)
        ->and($result->grade)->toBe('A');

    $labels = array_column($result->metrics, 'value', 'label');
    expect($labels)->toHaveKey('Largest Contentful Paint')
        ->and($labels['Largest Contentful Paint'])->toBe('1.2 s')
        ->and($labels)->toHaveKey('Cumulative Layout Shift');
});

it('surfaces the highest-impact opportunities as recommendations, worst first', function () {
    $result = (new PsiNormalizer())->normalize(psiPayload());

    expect($result->recommendations)->not->toBe([])
        ->and($result->recommendations[0])->toContain('unused JavaScript'); // 800ms savings > 400ms
});

it('degrades to a graceful recommended result on a malformed payload', function () {
    $result = (new PsiNormalizer())->normalize(['unexpected' => true]);

    expect($result->status->value)->toBe('recommended')
        ->and($result->recommendations)->not->toBe([]);
});
