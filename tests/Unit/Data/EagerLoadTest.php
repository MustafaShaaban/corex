<?php

/**
 * Unit tests for eager-load id collection + immutable relation attachment
 * (spec US4: FR-018, FR-020).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Database\QueryExecutor;
use Corex\Tests\Fixtures\Data\Job;

require_once __DIR__ . '/DataFixtures.php';

it('collects distinct belongs-to foreign-key ids and skips empties', function () {
    $models = [
        new Job(['id' => 1, 'company_id' => 10]),
        new Job(['id' => 2, 'company_id' => 10]), // duplicate id
        new Job(['id' => 3, 'company_id' => 20]),
        new Job(['id' => 4]),                      // no foreign key
    ];

    expect(QueryExecutor::collectRelatedIds($models, 'company_id'))->toBe([10, 20]);
});

it('attaches a relation immutably via withAttribute', function () {
    $job = new Job(['id' => 1, 'company_id' => 10]);
    $related = new Job(['id' => 10]);

    $withCompany = $job->withAttribute('company', $related);

    expect($withCompany->get('company'))->toBe($related)
        ->and($job->get('company'))->toBeNull(); // original unchanged
});
