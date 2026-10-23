<?php

/**
 * A PageSpeed run that could not complete has no score and no grade (spec 114, US-SAFE-12).
 *
 * It was stored as a completed run with a score of 50, which drew a D for a site the service had
 * never measured: a local address, a quota, a refused key, an unreachable service. A failed run
 * now says so, and failed runs stored by earlier releases are read as the failures they were,
 * without the stored option being rewritten.
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Insights\InsightFailure;
use Corex\Config\Insights\InsightResult;
use Corex\Config\Insights\InsightStore;
use Corex\Config\Insights\Normalizers\PsiNormalizer;
use Corex\Config\Insights\Providers\PerformanceProvider;
use Corex\Config\Insights\SiteUrlReachability;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/** A row as an earlier release stored a failed run. */
function storedBeforeFailure(string $summary, int $at = 1700000000): array
{
    return [
        'provider'        => 'performance',
        'label'           => 'Performance',
        'score'           => 50,
        'grade'           => 'D',
        'status'          => 'recommended',
        'summary'         => $summary,
        'metrics'         => [],
        'recommendations' => [$summary],
        'checkedAt'       => $at,
    ];
}

it('builds a failed result with its reason and no score, grade or recommendation', function () {
    $row = InsightResult::failed('performance', 'Performance', 'quota', 'PageSpeed quota was exceeded.', 123)->toArray();

    expect($row['score'])->toBeNull()
        ->and($row['grade'])->toBeNull()
        ->and($row['status'])->toBe('failed')
        ->and($row['failure'])->toBe('quota')
        ->and($row['summary'])->toBe('PageSpeed quota was exceeded.')
        ->and($row['recommendations'])->toBe([])
        ->and($row['checkedAt'])->toBe(123);
});

it('keeps a completed result as it was, with no failure', function () {
    $row = (new InsightResult('performance', 'Performance', 50, 'Lighthouse performance score: 50/100.'))->toArray();

    expect($row['score'])->toBe(50)
        ->and($row['grade'])->toBe('D')
        ->and($row)->not->toHaveKey('failure');
});

it('reads a local address as a failure without asking the service', function () {
    Functions\expect('wp_remote_get')->never();

    $result = (new PerformanceProvider(new PsiNormalizer(), new SiteUrlReachability()))->run('http://corex.local/');

    expect($result->isFailed())->toBeTrue()
        ->and($result->failure)->toBe('local_url')
        ->and($result->score)->toBeNull()
        ->and($result->summary)->toStartWith('PageSpeed cannot reach a local site');
});

it('reads a failed run stored by an earlier release as a failure with its message', function (string $summary) {
    $row = (new InsightFailure())->read(storedBeforeFailure($summary));

    expect($row['status'])->toBe('failed')
        ->and($row['failure'])->toBe(InsightFailure::LEGACY)
        ->and($row['score'])->toBeNull()
        ->and($row['grade'])->toBeNull()
        ->and($row['summary'])->toBe($summary)
        ->and($row['checkedAt'])->toBe(1700000000);
})->with([
    'a local address' => ['PageSpeed cannot crawl a local or private URL. Test a public URL instead.'],
    'the quota' => ['PageSpeed quota was exceeded. Add an API key, or wait and try again.'],
    'a refused key' => ['The PageSpeed API key was rejected. Check the key in Settings.'],
    'an unreadable answer' => ['PageSpeed returned an unreadable response. Try again shortly.'],
    'an HTTP status' => ['PageSpeed returned HTTP 503. Try again shortly.'],
    'no result in the answer' => ['The performance result could not be read.'],
]);

it('recognises the message in the site language it was stored in', function () {
    $arabic = 'تم تجاوز حصة PageSpeed. أضف مفتاحًا أو انتظر ثم أعد المحاولة.';

    expect((new InsightFailure([$arabic]))->read(storedBeforeFailure($arabic))['status'])->toBe('failed')
        ->and((new InsightFailure())->read(storedBeforeFailure($arabic))['status'])->toBe('recommended');
});

it('leaves a real score of 50 a score', function () {
    $measured = ['metrics' => [['label' => 'Largest Contentful Paint', 'value' => '4.1 s']]] + storedBeforeFailure('Lighthouse performance score: 50/100.');
    $unknown  = storedBeforeFailure('Lighthouse performance score: 50/100.');

    expect((new InsightFailure())->read($measured)['score'])->toBe(50)
        ->and((new InsightFailure())->read($unknown)['score'])->toBe(50);
});

it('reads stored history through the recogniser, and keeps every stored row', function () {
    $state = ['performance' => [
        'latest'  => storedBeforeFailure('PageSpeed returned HTTP 500. Try again shortly.', 3),
        'history' => [
            storedBeforeFailure('PageSpeed returned HTTP 500. Try again shortly.', 3),
            (new InsightResult('performance', 'Performance', 88, 'Lighthouse performance score: 88/100.', [], [], 2))->toArray(),
            storedBeforeFailure('PageSpeed quota was exceeded. Add an API key, or wait and try again.', 1),
        ],
    ]];
    $store = new InsightStore();

    expect($store->latest($state, 'performance')['status'])->toBe('failed')
        ->and(array_column($store->history($state, 'performance'), 'status'))->toBe(['failed', 'recommended', 'failed'])
        ->and(array_column($store->history($state, 'performance'), 'score'))->toBe([null, 88, null])
        // Read, never rewritten.
        ->and($state['performance']['history'][0]['score'])->toBe(50);
});
