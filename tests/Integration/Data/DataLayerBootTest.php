<?php

/**
 * Integration test: the data layer resolves through the container after boot, on
 * the real ./wp install with ACF absent (spec FR-021, SC-003).
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Database\QueryExecutor;
use Corex\Fields\FieldDriver;
use Corex\Fields\MetaFieldDriver;
use Corex\Repositories\Hydrator;
use Corex\Support\Facades\Config;

it('resolves the data layer through the container, using native meta when ACF is absent', function () {
    $container = Boot::app()->container();

    expect($container->make(FieldDriver::class))->toBeInstanceOf(MetaFieldDriver::class)
        ->and($container->make(Hydrator::class))->toBeInstanceOf(Hydrator::class)
        ->and($container->make(QueryExecutor::class))->toBeInstanceOf(QueryExecutor::class);
});

it('reads the query cap from the Config engine', function () {
    expect(Config::get('query.max'))->toBe(500)
        ->and(Boot::app()->container()->make(QueryExecutor::class)->maxResults())->toBe(500);
});
