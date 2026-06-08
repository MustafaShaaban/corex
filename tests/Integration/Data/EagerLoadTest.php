<?php

/**
 * Integration test: belongs-to eager loading populates relations in a bounded
 * query count — no N+1 (spec US4: FR-018, FR-019, FR-020, SC-004).
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Database\QueryBuilder;
use Corex\Database\QueryExecutor;
use Corex\Fields\MetaFieldDriver;
use Corex\Repositories\Hydrator;
use Corex\Tests\Fixtures\Data\Company;
use Corex\Tests\Fixtures\Data\Job;

require_once dirname(__DIR__, 2) . '/Unit/Data/DataFixtures.php';

function eagerExecutor(): QueryExecutor
{
    return new QueryExecutor(new Hydrator(new MetaFieldDriver()));
}

/** @return list<int> */
function makeJobs(int $count, int $companyId): array
{
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
        $jobId = wp_insert_post(['post_type' => 'job', 'post_status' => 'publish', 'post_title' => "Job {$i}"]);
        update_post_meta($jobId, 'company_id', $companyId);
        $ids[] = $jobId;
    }

    return $ids;
}

function deleteAll(int ...$ids): void
{
    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }
}

it('populates the belongs-to relation on every model', function () {
    $companyId = wp_insert_post(['post_type' => 'company', 'post_status' => 'publish', 'post_title' => 'Acme']);
    $jobIds = makeJobs(3, $companyId);

    $result = (new QueryBuilder(Job::class, eagerExecutor(), 500))
        ->where('company_id', $companyId)
        ->with('company')
        ->get();

    expect($result->count())->toBe(3);
    foreach ($result->all() as $job) {
        expect($job->get('company'))->toBeInstanceOf(Company::class)
            ->and($job->get('company')->id())->toBe($companyId);
    }

    deleteAll($companyId, ...$jobIds);
});

it('uses a bounded query count regardless of N (no N+1)', function () {
    global $wpdb;
    $companyId = wp_insert_post(['post_type' => 'company', 'post_status' => 'publish', 'post_title' => 'Acme']);

    $small = makeJobs(2, $companyId);
    $before = $wpdb->num_queries;
    (new QueryBuilder(Job::class, eagerExecutor(), 500))->where('company_id', $companyId)->with('company')->get();
    $queriesForTwo = $wpdb->num_queries - $before;
    deleteAll(...$small);

    $large = makeJobs(50, $companyId);
    $before = $wpdb->num_queries;
    (new QueryBuilder(Job::class, eagerExecutor(), 500))->where('company_id', $companyId)->with('company')->get();
    $queriesForFifty = $wpdb->num_queries - $before;
    deleteAll($companyId, ...$large);

    // Constant in N: 50 entities must not cost ~48 more queries than 2.
    expect($queriesForFifty)->toBeLessThanOrEqual($queriesForTwo + 2);
});

it('reports an empty relation when the foreign key points nowhere', function () {
    $orphan = wp_insert_post(['post_type' => 'job', 'post_status' => 'publish', 'post_title' => 'Orphan']);
    update_post_meta($orphan, 'company_id', 999999);

    $result = (new QueryBuilder(Job::class, eagerExecutor(), 500))
        ->where('company_id', 999999)
        ->with('company')
        ->get();

    expect($result->first()->get('company'))->toBeNull();

    deleteAll($orphan);
});
