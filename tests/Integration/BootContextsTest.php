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
use Corex\Support\Facades\Config;
use Corex\Support\Config\ConfigInterface;

it('self-boots once on plugins_loaded with no fatals', function () {
    expect(did_action('plugins_loaded'))->toBeGreaterThan(0)
        ->and(Boot::app())->toBeInstanceOf(Application::class)
        ->and(Boot::app()->isBooted())->toBeTrue();
});

it('exposes a working container that resolves foundation services', function () {
    $container = Boot::app()->container();

    expect($container)->toBeInstanceOf(Container::class)
        ->and($container->make(BootLogger::class))->toBeInstanceOf(BootLogger::class)
        ->and($container->make(ConfigInterface::class))->toBeInstanceOf(ConfigInterface::class);
});

it('resolves layered configuration through the Config facade', function () {
    // No .env, no override option → the shipped default wins.
    expect(Config::get('app.name'))->toBe('Corex')
        ->and(Config::get('does.not.exist', 'fallback'))->toBe('fallback');
});

it('wires controller discovery (an empty core Controllers dir is non-fatal)', function () {
    $map = Boot::app()->container()->make(\Corex\Http\ControllerMap::class);

    expect($map)->toBeInstanceOf(\Corex\Http\ControllerMap::class)
        ->and($map->controllers())->toBeArray();
});
