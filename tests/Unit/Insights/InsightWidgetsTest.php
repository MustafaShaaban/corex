<?php

/**
 * Unit tests for the pure Insights widget model (spec 068: T185). No WordPress.
 * Contract: every widget state derives from real facts — Forms & Flows analytics now
 * projects live submission/flow counts instead of a "planned" placeholder.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Insights\InsightWidgets;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
});

/**
 * @return array<string,mixed>
 */
function insightFacts(array $overrides = []): array
{
    return array_merge([
        'psiKeyConfigured'       => true,
        'cfConfigured'           => false,
        'performanceLatest'      => null,
        'readinessLatest'        => null,
        'searchVisible'          => true,
        'prettyPermalinks'       => true,
        'securityEvents'         => [],
        'cronDisabledByConstant' => false,
        'cronOverdue'            => 0,
        'phpVersion'             => '8.3.0',
        'wpVersion'              => '7.0',
        'environment'            => 'development',
        'operationsMode'         => 'development',
        'modeDeclared'           => true,
        'formsSubmissions'       => 58,
        'formsPublishedFlows'    => 1,
        'formsTotalFlows'        => 3,
    ], $overrides);
}

it('builds the seven designed widgets with distinct ids and no planned state', function () {
    $widgets = (new InsightWidgets())->widgets(insightFacts());
    $ids     = array_column($widgets, 'key');
    $states  = array_column($widgets, 'state');

    expect($ids)->toBe(['performance', 'cloudflare', 'security', 'seo', 'ai', 'ops', 'forms'])
        ->and($states)->not->toContain('planned');
});

it('projects real Forms & Flows analytics rows instead of a planned placeholder', function () {
    $widgets = (new InsightWidgets())->widgets(insightFacts());
    $forms   = array_values(array_filter($widgets, static fn (array $w): bool => $w['key'] === 'forms'))[0];

    expect($forms['state'])->toBe(InsightWidgets::STATE_LIVE)
        ->and($forms['alt'])->toBeNull()
        ->and($forms['rows'])->toHaveCount(3)
        ->and($forms['rows'][0])->toMatchArray(['label' => 'Stored submissions', 'value' => '58'])
        ->and($forms['rows'][1])->toMatchArray(['label' => 'Published flows', 'value' => '1', 'tone' => 'success']);
});

it('clamps negative forms counts and marks zero published flows as a subtle, not-success, state', function () {
    $widgets = (new InsightWidgets())->widgets(insightFacts(['formsSubmissions' => -3, 'formsPublishedFlows' => 0]));
    $forms   = array_values(array_filter($widgets, static fn (array $w): bool => $w['key'] === 'forms'))[0];

    expect($forms['rows'][0]['value'])->toBe('0')
        ->and($forms['rows'][1])->toMatchArray(['value' => '0', 'tone' => 'subtle']);
});
