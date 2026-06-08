<?php

/**
 * Integration test: QueryExecutor runs a real WP_Query and hydrates Models
 * (spec US3: FR-013, FR-017).
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Database\Collection;
use Corex\Database\QueryBuilder;
use Corex\Database\QueryExecutor;
use Corex\Fields\MetaFieldDriver;
use Corex\Repositories\Hydrator;
use Corex\Tests\Fixtures\Data\Job;

require_once dirname(__DIR__, 2) . '/Unit/Data/DataFixtures.php';

function jobExecutor(): QueryExecutor
{
    return new QueryExecutor(new Hydrator(new MetaFieldDriver()));
}

it('runs a real query and returns a Collection of hydrated Models', function () {
    $first = wp_insert_post(['post_type' => 'job', 'post_title' => 'Alpha', 'post_status' => 'publish']);
    update_post_meta($first, 'job_salary', 100);
    $second = wp_insert_post(['post_type' => 'job', 'post_title' => 'Beta', 'post_status' => 'publish']);
    update_post_meta($second, 'job_salary', 200);

    $result = (new QueryBuilder(Job::class, jobExecutor(), 500))
        ->where('post_status', 'publish')
        ->get();

    $salaries = array_map(static fn (Job $job) => $job->get('salary'), $result->all());

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($salaries)->toContain(100)
        ->and($salaries)->toContain(200);

    wp_delete_post($first, true);
    wp_delete_post($second, true);
});

it('returns an empty Collection when nothing matches', function () {
    $result = (new QueryBuilder(Job::class, jobExecutor(), 500))
        ->where('post_status', 'no-such-status-xyz')
        ->get();

    expect($result->isEmpty())->toBeTrue();
});
