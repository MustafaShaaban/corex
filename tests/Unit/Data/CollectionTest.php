<?php

/**
 * Unit tests for the Collection result set (spec US3: FR-017).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Database\Collection;
use Corex\Tests\Fixtures\Data\Job;

require_once __DIR__ . '/DataFixtures.php';

it('reports first, count, emptiness, and iterates in order', function () {
    $a = new Job(['id' => 1]);
    $b = new Job(['id' => 2]);
    $collection = new Collection([$a, $b]);

    expect($collection->count())->toBe(2)
        ->and($collection->first())->toBe($a)
        ->and($collection->isEmpty())->toBeFalse()
        ->and(iterator_to_array($collection))->toBe([$a, $b]);
});

it('is empty (not null/error) when nothing matches', function () {
    $empty = new Collection([]);

    expect($empty->isEmpty())->toBeTrue()
        ->and($empty->first())->toBeNull()
        ->and($empty->count())->toBe(0)
        ->and($empty->all())->toBe([]);
});
