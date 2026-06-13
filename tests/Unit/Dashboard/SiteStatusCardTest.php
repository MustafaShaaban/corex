<?php

/**
 * Unit tests for the pure dashboard Site-status card view model (spec 042). No WordPress.
 *
 * @package Corex\Tests\Unit\Dashboard
 */

declare(strict_types=1);

use Corex\Config\Dashboard\SiteStatusCard;

it('reports applied kits, the submission count, and the front-page status', function () {
    $model = (new SiteStatusCard())->model(['Company'], 34, 'http://x/data', SiteStatusCard::FRONT_COREX_PAGE);

    expect($model['appliedKits'])->toBe(['Company'])
        ->and($model['submissionCount'])->toBe(34)
        ->and($model['submissionsUrl'])->toBe('http://x/data')
        ->and($model['frontPage'])->toBe(SiteStatusCard::FRONT_COREX_PAGE)
        ->and($model['isEmptyState'])->toBeFalse();
});

it('shows the empty state when nothing is applied and there are no submissions', function () {
    $model = (new SiteStatusCard())->model([], 0, 'http://x/data', SiteStatusCard::FRONT_BLOG_INDEX);

    expect($model['isEmptyState'])->toBeTrue();
});

it('is not an empty state when submissions exist even with no applied kit', function () {
    $model = (new SiteStatusCard())->model([], 5, 'http://x/data', SiteStatusCard::FRONT_BLANK);

    expect($model['isEmptyState'])->toBeFalse();
});
