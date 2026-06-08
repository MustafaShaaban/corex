<?php

/**
 * Unit tests for the service-provider boot lifecycle (spec US1: FR-002, FR-004, FR-023).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Container\ContainerInterface;
use Corex\Foundation\Application;
use Corex\Foundation\ProviderRepository;
use Corex\Hooks\HookRegistry;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Providers\ProviderA;
use Corex\Tests\Fixtures\Providers\ProviderB;
use Corex\Tests\Fixtures\Providers\Recorder;
use Corex\Tests\Fixtures\Providers\SubscribingProvider;
use Corex\Tests\Fixtures\Providers\ThrowingProvider;

require_once __DIR__ . '/ProviderFixtures.php';

beforeEach(fn () => Recorder::reset());

/**
 * @return array{0: ProviderRepository, 1: BootLogger}
 */
function makeRepository(?BootLogger $logger = null): array
{
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $logger ??= new BootLogger(debug: false);

    return [new ProviderRepository($container, $logger, new HookRegistry($container)), $logger];
}

it('registers all providers before booting any of them', function () {
    [$repository] = makeRepository();

    $repository->load([ProviderA::class, ProviderB::class]);

    expect(Recorder::$calls)->toBe(['A::register', 'B::register', 'A::boot', 'B::boot']);
});

it('loads each provider only once', function () {
    [$repository] = makeRepository();

    $repository->load([ProviderA::class, ProviderA::class]);

    expect(array_filter(Recorder::$calls, fn ($c) => $c === 'A::register'))->toHaveCount(1);
});

it('isolates a failing provider and keeps booting the others', function () {
    [$repository, $logger] = makeRepository();

    $repository->load([ThrowingProvider::class, ProviderA::class]);

    expect(Recorder::$calls)->toContain('A::boot')
        ->and($logger->messages())->not->toBeEmpty();
});

it('boots the application once and exposes its container', function () {
    $app = new Application(debug: false, providers: [ProviderA::class]);

    $app->boot();
    $app->boot();

    expect(array_filter(Recorder::$calls, fn ($c) => $c === 'A::register'))->toHaveCount(1)
        ->and($app->container())->toBeInstanceOf(Container::class);
});

it('wires a provider hook subscribers during the boot pass', function () {
    Functions\expect('add_filter')->once()->with('init', \Mockery::type('array'), 10, 1);

    (new Application(debug: false, providers: [SubscribingProvider::class]))->boot();
});
