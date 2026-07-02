<?php

/**
 * Unit tests for the pure Overview operational-summary view model (spec 063, Phase 1). No WordPress.
 * The core contract: truthful rows only — absent facts become honest empty rows, never invented values.
 *
 * @package Corex\Tests\Unit\Overview
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Overview\OverviewSummary;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->summary = new OverviewSummary();
});

/**
 * @param list<array{key:string,label:string,value:string,tone:string,detail:string,url:string}> $rows
 *
 * @return array{key:string,label:string,value:string,tone:string,detail:string,url:string}
 */
function overviewRow(array $rows, string $key): array
{
    foreach ($rows as $row) {
        if ($row['key'] === $key) {
            return $row;
        }
    }

    throw new RuntimeException("No overview row with key {$key}");
}

/**
 * @return array{environment:array{mode:string,label:string,tone:string,detail:string},addons:array{active:int,total:int},submissions:int|null,media:string,docsUrl:string,insightsUrl:string}
 */
function overviewFacts(array $overrides = []): array
{
    return array_merge([
        'environment' => ['mode' => 'production', 'label' => 'Production', 'tone' => 'success', 'detail' => 'Live.'],
        'addons'      => ['active' => 2, 'total' => 5],
        'submissions' => 12,
        'media'       => 'WebP: Supported',
        'docsUrl'     => 'https://example.test/docs/',
        'insightsUrl' => 'admin.php?page=corex-insights',
    ], $overrides);
}

it('returns one row per summary signal, environment first', function () {
    $rows = (new OverviewSummary())->rows(overviewFacts());

    expect($rows)->toHaveCount(6)
        ->and($rows[0]['key'])->toBe('environment')
        ->and(array_column($rows, 'key'))
        ->toBe(['environment', 'addons', 'submissions', 'media', 'readiness', 'docs']);
});

it('reports the real active/total add-on counts', function () {
    $rows = $this->summary->rows(overviewFacts(['addons' => ['active' => 3, 'total' => 7]]));
    $addons = overviewRow($rows, 'addons');

    expect($addons['value'])->toContain('3')->toContain('7');
});

it('shows an honest neutral state when no add-ons are registered', function () {
    $rows = $this->summary->rows(overviewFacts(['addons' => ['active' => 0, 'total' => 0]]));
    $addons = overviewRow($rows, 'addons');

    expect($addons['tone'])->toBe(OverviewSummary::TONE_NEUTRAL)
        ->and($addons['value'])->not->toContain('0 active');
});

it('renders submissions as a truthful count when data exists', function () {
    $rows = $this->summary->rows(overviewFacts(['submissions' => 8]));
    $sub  = overviewRow($rows, 'submissions');

    expect($sub['value'])->toBe('8')->and($sub['url'])->toBe('admin.php?page=corex-data');
});

it('shows "not available" for submissions when the source is unavailable — never a fabricated zero-as-real', function () {
    $rows = $this->summary->rows(overviewFacts(['submissions' => null]));
    $sub  = overviewRow($rows, 'submissions');

    expect($sub['value'])->toBe('Not available')
        ->and($sub['tone'])->toBe(OverviewSummary::TONE_NEUTRAL);
});

it('shows an honest empty media row when the optimization add-on is inactive', function () {
    $rows  = $this->summary->rows(overviewFacts(['media' => '']));
    $media = overviewRow($rows, 'media');

    expect($media['value'])->toBe('Optimization inactive')
        ->and($media['tone'])->toBe(OverviewSummary::TONE_NEUTRAL);
});

it('surfaces the real media support summary when the add-on reports one', function () {
    $rows  = $this->summary->rows(overviewFacts(['media' => 'WebP: Supported']));
    $media = overviewRow($rows, 'media');

    expect($media['value'])->toBe('WebP: Supported');
});

it('links readiness to the internal Insights admin path without inventing a score', function () {
    $rows      = $this->summary->rows(overviewFacts());
    $readiness = overviewRow($rows, 'readiness');

    expect($readiness['value'])->toBe('Not checked yet')
        ->and($readiness['url'])->toBe('admin.php?page=corex-insights')
        ->and($readiness['external'])->toBeFalse();
});

it('carries the resolved documentation url on the docs row and marks it external', function () {
    $rows = $this->summary->rows(overviewFacts(['docsUrl' => 'https://docs.example/']));
    $docs = overviewRow($rows, 'docs');

    expect($docs['url'])->toBe('https://docs.example/')
        ->and($docs['external'])->toBeTrue();
});

it('marks internal admin rows as non-external so the boundary resolves them via admin_url', function () {
    $rows = $this->summary->rows(overviewFacts());

    foreach (['environment', 'addons', 'submissions', 'media', 'readiness'] as $key) {
        expect(overviewRow($rows, $key)['external'])->toBeFalse();
    }
});
