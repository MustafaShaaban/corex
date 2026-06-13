<?php

/**
 * Unit tests for the data query value object (spec 045: US1, FR-001).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Config\Data\DataQuery;

it('applies sane defaults from empty params', function () {
    $q = DataQuery::from([]);

    expect($q->search)->toBe('')
        ->and($q->filters)->toBe([])
        ->and($q->sortColumn)->toBe('')
        ->and($q->sortDir)->toBe('desc')
        ->and($q->page)->toBe(1)
        ->and($q->perPage)->toBe(20);
});

it('clamps the page to at least 1', function () {
    expect(DataQuery::from(['page' => 0])->page)->toBe(1)
        ->and(DataQuery::from(['page' => -5])->page)->toBe(1)
        ->and(DataQuery::from(['page' => 3])->page)->toBe(3);
});

it('clamps per_page to the 1..max range', function () {
    expect(DataQuery::from(['per_page' => 500])->perPage)->toBe(DataQuery::MAX_PER_PAGE)
        ->and(DataQuery::from(['per_page' => 0])->perPage)->toBe(1)
        ->and(DataQuery::from(['per_page' => 50])->perPage)->toBe(50);
});

it('normalises the sort direction to asc or desc', function () {
    expect(DataQuery::from(['dir' => 'asc'])->sortDir)->toBe('asc')
        ->and(DataQuery::from(['dir' => 'ASC'])->sortDir)->toBe('asc')
        ->and(DataQuery::from(['dir' => 'sideways'])->sortDir)->toBe('desc');
});

it('accepts a form shortcut as a filter and trims the search', function () {
    $q = DataQuery::from(['form' => 'contact', 'search' => '  hello  ', 'sort' => 'date']);

    expect($q->filters)->toBe(['form' => 'contact'])
        ->and($q->search)->toBe('hello')
        ->and($q->sortColumn)->toBe('date');
});
