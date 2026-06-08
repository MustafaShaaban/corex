<?php

/**
 * Unit tests for the QueryBuilder arg-builder (spec US3: FR-013–FR-016, SC-005, SC-006).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Database\QueryBuilder;
use Corex\Database\QueryExecutor;
use Corex\Repositories\Hydrator;
use Corex\Tests\Fixtures\Data\FakeFieldDriver;
use Corex\Tests\Fixtures\Data\Job;

require_once __DIR__ . '/DataFixtures.php';

function jobQuery(int $cap = 500): QueryBuilder
{
    $executor = new QueryExecutor(new Hydrator(new FakeFieldDriver()));

    return new QueryBuilder(Job::class, $executor, $cap);
}

it('caps posts_per_page and never emits -1', function () {
    expect(jobQuery()->toArgs()['posts_per_page'])->toBe(500)
        ->and(jobQuery()->limit(10000)->toArgs()['posts_per_page'])->toBe(500)
        ->and(jobQuery()->limit(20)->toArgs()['posts_per_page'])->toBe(20);
});

it('sets the post type and disables found-rows', function () {
    $args = jobQuery()->toArgs();

    expect($args['post_type'])->toBe('job')
        ->and($args['no_found_rows'])->toBeTrue();
});

it('routes a declared field into meta_query, bound as data', function () {
    $args = jobQuery()->where('salary', "80000' OR 1=1", '>=')->toArgs();

    expect($args['meta_query'][0])->toBe([
        'key'     => 'job_salary',
        'value'   => "80000' OR 1=1",
        'compare' => '>=',
    ]);
});

it('passes a core field through as a WP_Query arg', function () {
    expect(jobQuery()->where('post_status', 'publish')->toArgs()['post_status'])->toBe('publish');
});

it('maps orderBy on a declared field to meta_value + meta_key', function () {
    $args = jobQuery()->orderBy('salary', 'DESC')->toArgs();

    expect($args['orderby'])->toBe('meta_value')
        ->and($args['meta_key'])->toBe('job_salary')
        ->and($args['order'])->toBe('DESC');
});
