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

it('orders by a declared field numerically (meta_value_num) when asked', function () {
    $args = jobQuery()->orderByNumeric('salary', 'asc')->toArgs();

    expect($args['orderby'])->toBe('meta_value_num')
        ->and($args['meta_key'])->toBe('job_salary');
});

it('combines multiple meta conditions under an AND relation', function () {
    $args = jobQuery()
        ->where('salary', 50000, '>=')
        ->where('company_id', 5)
        ->toArgs();

    expect($args['meta_query']['relation'])->toBe('AND')
        ->and($args['meta_query'][0]['key'])->toBe('job_salary')
        ->and($args['meta_query'][1]['key'])->toBe('company_id');
});

it('switches the meta relation to OR via orWhere', function () {
    $args = jobQuery()
        ->where('salary', 50000, '>=')
        ->orWhere('salary', 30000, '<=')
        ->toArgs();

    expect($args['meta_query']['relation'])->toBe('OR');
});

it('builds a numeric BETWEEN range bound as data', function () {
    $clause = jobQuery()->whereBetween('salary', 40000, 90000)->toArgs()['meta_query'][0];

    expect($clause)->toBe([
        'key'     => 'job_salary',
        'value'   => [40000, 90000],
        'compare' => 'BETWEEN',
        'type'    => 'NUMERIC',
    ]);
});

it('adds a raw-meta condition with an explicit type', function () {
    $clause = jobQuery()->whereMeta('featured', '1', '=', 'NUMERIC')->toArgs()['meta_query'][0];

    expect($clause)->toBe(['key' => 'featured', 'value' => '1', 'compare' => '=', 'type' => 'NUMERIC']);
});

it('builds a taxonomy query, and an OR relation across several', function () {
    $single = jobQuery()->whereTax('department', [3, 4])->toArgs();
    expect($single['tax_query'][0])->toBe([
        'taxonomy' => 'department',
        'field'    => 'term_id',
        'terms'    => [3, 4],
        'operator' => 'IN',
    ]);

    $multi = jobQuery()
        ->whereTax('department', 'engineering', 'slug')
        ->whereTax('location', 'remote', 'slug')
        ->taxRelation('OR')
        ->toArgs();
    expect($multi['tax_query']['relation'])->toBe('OR')
        ->and($multi['tax_query'][0]['field'])->toBe('slug');
});

it('restricts by a post-date range', function () {
    $args = jobQuery()->whereDate('2026-01-01', '2026-06-30')->toArgs();

    expect($args['date_query'][0])->toBe([
        'inclusive' => true,
        'after'     => '2026-01-01',
        'before'    => '2026-06-30',
    ]);
});

it('passes a search term through as the WP_Query s arg', function () {
    expect(jobQuery()->search('senior php')->toArgs()['s'])->toBe('senior php');
});

it('paginates: caps per-page, sets the page, and enables found-rows', function () {
    $args = jobQuery()->paginate(10, 3)->toArgs();

    expect($args['posts_per_page'])->toBe(10)
        ->and($args['paged'])->toBe(3)
        ->and($args['no_found_rows'])->toBeFalse();

    // Per-page is still capped.
    expect(jobQuery()->paginate(100000, 1)->toArgs()['posts_per_page'])->toBe(500);
});

it('composes meta, tax, date, search, order, and pagination into one args array', function () {
    $args = jobQuery()
        ->where('salary', 50000, '>=')
        ->whereTax('department', [7])
        ->whereDate('2026-01-01')
        ->search('engineer')
        ->orderByNumeric('salary', 'desc')
        ->paginate(20, 2)
        ->toArgs();

    expect($args)
        ->toHaveKeys(['post_type', 'meta_query', 'tax_query', 'date_query', 's', 'orderby', 'meta_key', 'paged'])
        ->and($args['post_type'])->toBe('job')
        ->and($args['posts_per_page'])->toBe(20)
        ->and($args['no_found_rows'])->toBeFalse();
});
