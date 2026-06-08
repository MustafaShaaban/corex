<?php

/**
 * Unit tests for controller auto-discovery (spec US4: FR-018–FR-020, SC-005).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Container\Container;
use Corex\Http\ControllerMap;
use Corex\Tests\Fixtures\Controllers\AbstractBaseController;
use Corex\Tests\Fixtures\Controllers\ControllerContract;
use Corex\Tests\Fixtures\Controllers\PingController;

const CONTROLLER_FIXTURE_NS = 'Corex\\Tests\\Fixtures\\Controllers\\';

function controllerFixtureDir(): string
{
    return dirname(__DIR__, 2) . '/Fixtures/Controllers';
}

it('discovers an instantiable controller and makes it resolvable', function () {
    $container = new Container();
    $map = new ControllerMap($container);

    $map->discover([CONTROLLER_FIXTURE_NS => controllerFixtureDir()]);

    expect($map->controllers())->toContain(PingController::class)
        ->and($container->make(PingController::class))->toBeInstanceOf(PingController::class);
});

it('skips abstract classes and interfaces in the controllers directory', function () {
    $map = new ControllerMap(new Container());
    $map->discover([CONTROLLER_FIXTURE_NS => controllerFixtureDir()]);

    expect($map->controllers())->not->toContain(AbstractBaseController::class)
        ->and($map->controllers())->not->toContain(ControllerContract::class);
});

it('finds nothing when the controllers directory is empty', function () {
    $empty = sys_get_temp_dir() . '/corex_controllers_' . uniqid('', true);
    mkdir($empty);

    $map = new ControllerMap(new Container());
    $map->discover(['Acme\\Empty\\' => $empty]);

    expect($map->controllers())->toBe([]);

    rmdir($empty);
});

it('finds nothing when the directory does not exist', function () {
    $map = new ControllerMap(new Container());
    $map->discover(['Acme\\Missing\\' => '/no/such/dir']);

    expect($map->controllers())->toBe([]);
});
