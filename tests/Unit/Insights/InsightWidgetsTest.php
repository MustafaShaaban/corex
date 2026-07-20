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
    $states  = array_column($widgets, 'state');

    expect(array_column($widgets, 'key'))
        ->toHaveCount(7)
        ->and(array_unique(array_column($widgets, 'key')))->toHaveCount(7)
        ->and($states)->not->toContain('planned');
});

it('orders widgets by urgency, with nothing-to-show last', function () {
    // FR-027. Registration order put an unconfigured Cloudflare second and buried the widgets
    // that actually had something to say. With the default facts: seo/ops/forms are live,
    // performance/security/ai have no data yet, and cloudflare is unconfigured.
    $widgets = (new InsightWidgets())->widgets(insightFacts());

    expect(array_column($widgets, 'key'))
        ->toBe(['seo', 'ops', 'forms', 'performance', 'security', 'ai', 'cloudflare']);
});

it('floats a widget wanting attention above everything else', function () {
    // A hidden site is the reason someone opens this screen; it must not sit below three
    // healthy widgets. searchVisible false gives SEO a warning chip.
    $widgets = (new InsightWidgets())->widgets(insightFacts([
        'searchVisible' => false,
        'cronOverdue'   => 4,
    ]));
    $keys = array_column($widgets, 'key');

    expect(array_slice($keys, 0, 2))->toBe(['seo', 'ops'])
        ->and($widgets[0]['chipTone'])->toBe('warning')
        ->and($widgets[1]['chipTone'])->toBe('warning');
});

it('keeps registration order within one urgency rank', function () {
    // usort() is not stable for equal elements. Without the index tiebreak the screen would
    // reshuffle equally-urgent widgets between requests for no visible reason.
    $widgets = (new InsightWidgets())->widgets(insightFacts());
    $live    = array_values(array_filter(
        $widgets,
        static fn (array $w): bool => $w['state'] === InsightWidgets::STATE_LIVE && $w['chipTone'] === 'success',
    ));

    // seo (built 4th), ops (6th), forms (7th) — relative order preserved.
    expect(array_column($live, 'key'))->toBe(['seo', 'ops', 'forms']);
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
