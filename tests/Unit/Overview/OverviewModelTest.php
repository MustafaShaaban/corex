<?php

/**
 * Unit tests for the pure Overview readiness model (spec 064). No WordPress.
 * Contract: compose the dashboard from real facts only — truthful readiness, integrations, tiles,
 * data sources; never fabricate a count, an integration, or a score.
 *
 * @package Corex\Tests\Unit\Overview
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Overview\OverviewModel;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
});

/**
 * @return array{counts:array{posts:int,pages:int,submissions:int|null,addonsActive:int,addonsTotal:int},domains:array<string,bool>,frontPageSet:bool,kitApplied:bool,hardeningWarnings:int,dataSources:list<array{label:string,key:string,total:int}>,formsCount:int}
 */
function overviewFacts(array $overrides = []): array
{
    return array_merge([
        'counts'            => ['posts' => 4, 'pages' => 6, 'submissions' => 12, 'addonsActive' => 3, 'addonsTotal' => 9],
        'domains'           => ['brand' => true, 'mail' => true, 'captcha' => false, 'insights' => false, 'forms' => true],
        'frontPageSet'      => true,
        'kitApplied'        => true,
        'hardeningWarnings' => 0,
        'dataSources'       => [['label' => 'Submissions', 'key' => 'submissions', 'total' => 12]],
        'formsCount'        => 1,
    ], $overrides);
}

it('builds the five dashboard regions from real facts', function () {
    $data = (new OverviewModel())->build(overviewFacts());

    expect(array_keys($data))->toBe(['tiles', 'readiness', 'integrations', 'dataSources', 'forms'])
        ->and($data['tiles'])->toHaveCount(4)
        ->and($data['dataSources'][0]['count'])->toBe(12)
        ->and($data['forms']['count'])->toBe(1);
});

it('reports submissions as an em dash when the forms source is unavailable — never a fabricated zero', function () {
    $data = (new OverviewModel())->build(overviewFacts(['counts' => ['posts' => 0, 'pages' => 0, 'submissions' => null, 'addonsActive' => 0, 'addonsTotal' => 0]]));
    $submissionTile = $data['tiles'][2];

    expect($submissionTile['value'])->toBe('—')
        ->and($submissionTile['detail'])->toContain('inactive');
});

it('counts readiness from real signals and never overstates it', function () {
    $done = (new OverviewModel())->build(overviewFacts())['readiness'];
    // brand + kit + frontPage + mail done; captcha off; hardening ok -> 5 of 6.
    expect($done['total'])->toBe(6)
        ->and($done['done'])->toBe(5);

    $blank = (new OverviewModel())->build(overviewFacts([
        'domains' => ['brand' => false, 'mail' => false, 'captcha' => false, 'insights' => false],
        'frontPageSet' => false,
        'kitApplied' => false,
        'hardeningWarnings' => 2,
    ]))['readiness'];
    expect($blank['done'])->toBe(0);
});

it('shows honest connected / not-connected integration states, never a score', function () {
    $data = (new OverviewModel())->build(overviewFacts(['domains' => ['insights' => false, 'captcha' => true, 'mail' => false], 'hardeningWarnings' => 1]));
    $byLabel = [];
    foreach ($data['integrations'] as $row) {
        $byLabel[$row['label']] = $row;
    }

    expect($byLabel['Insights / PageSpeed']['note'])->toBe('Not connected')
        ->and($byLabel['Insights / PageSpeed']['tone'])->toBe(OverviewModel::TONE_NEUTRAL)
        ->and($byLabel['Captcha']['tone'])->toBe(OverviewModel::TONE_SUCCESS)
        ->and($byLabel['Login protection']['tone'])->toBe(OverviewModel::TONE_WARNING);
});

it('clamps negative counts and never renders a negative data-source total', function () {
    $data = (new OverviewModel())->build(overviewFacts(['dataSources' => [['label' => 'X', 'key' => 'x', 'total' => -3]]]));

    expect($data['dataSources'][0]['count'])->toBe(0);
});
