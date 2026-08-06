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

/**
 * The guard that spec 100 discovered was missing. `ProviderRepository::load()` catches every
 * Throwable from a provider's register() and boot() and records it on the BootLogger
 * (ProviderRepository.php:75, :89), so a provider that cannot be constructed at all degrades to a
 * silently absent feature rather than a white screen — which is the right runtime behaviour and the
 * wrong test behaviour. Nothing read those messages back, so a wiring break was invisible to a
 * fully green suite: spec 100 added a tenth constructor argument to OverviewRenderer and left its
 * explicit nine-argument factory in ConfigServiceProvider untouched, and 1809 passing tests had
 * nothing to say about a dashboard that would not render.
 *
 * Assert on the messages, not just on the absence of a fatal.
 */
it('boots every provider without logging an error', function () {
    $errors = array_values(array_filter(
        Boot::app()->container()->make(BootLogger::class)->messages(),
        static fn (array $message): bool => $message['level'] === 'error',
    ));

    expect($errors)->toBe([], 'Boot logged: ' . implode(' | ', array_column($errors, 'message')));
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
