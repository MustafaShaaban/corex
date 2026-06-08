<?php

/**
 * Integration test: corex-core self-boots inside a real WordPress runtime
 * (spec US1: FR-001, FR-003, SC-001).
 *
 * @package Corex\Tests\Integration
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Container\Container;
use Corex\Foundation\Application;
use Corex\Support\BootLogger;

it('self-boots once on plugins_loaded with no fatals', function () {
    expect(did_action('plugins_loaded'))->toBeGreaterThan(0)
        ->and(Boot::app())->toBeInstanceOf(Application::class)
        ->and(Boot::app()->isBooted())->toBeTrue();
});

it('exposes a working container that resolves foundation services', function () {
    $container = Boot::app()->container();

    expect($container)->toBeInstanceOf(Container::class)
        ->and($container->make(BootLogger::class))->toBeInstanceOf(BootLogger::class);
});
