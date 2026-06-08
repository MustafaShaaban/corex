<?php

/**
 * Unit tests for name normalization + validation (spec FR-009, FR-010).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Support\InvalidNameException;
use Corex\Cli\Support\Naming;

it('normalizes a name and applies the conventional suffix once', function () {
    $naming = new Naming();

    expect($naming->classNameFor('Career', 'Repository'))->toBe('CareerRepository')
        ->and($naming->classNameFor('CareerRepository', 'Repository'))->toBe('CareerRepository')
        ->and($naming->classNameFor('Career'))->toBe('Career');
});

it('rejects empty, non-identifier, and reserved-word names', function () {
    $naming = new Naming();

    expect(fn () => $naming->classNameFor(''))->toThrow(InvalidNameException::class);
    expect(fn () => $naming->classNameFor('9bad'))->toThrow(InvalidNameException::class);
    expect(fn () => $naming->classNameFor('has space'))->toThrow(InvalidNameException::class);
    expect(fn () => $naming->classNameFor('class'))->toThrow(InvalidNameException::class);
});

it('derives a snake_case post type from a class name', function () {
    expect((new Naming())->postTypeFor('CareerListing'))->toBe('career_listing');
});
