<?php

/**
 * Unit tests for declarative hook registration (spec US3: FR-015–FR-017, SC-004).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Hooks\HookRegistry;
use Corex\Tests\Fixtures\Hooks\ActionSubscriber;
use Corex\Tests\Fixtures\Hooks\FilterSubscriber;

require_once __DIR__ . '/HookFixtures.php';

it('wires an action method at the default priority and argument count', function () {
    $registry = new HookRegistry(new Container());

    Functions\expect('add_filter')->once()->with(
        'init',
        \Mockery::on(fn ($callback) => is_array($callback) && $callback[0] instanceof ActionSubscriber && $callback[1] === 'onInit'),
        10,
        1
    );

    $registry->register(ActionSubscriber::class);
});

it('wires a filter at its declared priority and accepted args', function () {
    $registry = new HookRegistry(new Container());

    Functions\expect('add_filter')->once()->with(
        'the_title',
        \Mockery::on(fn ($callback) => $callback[1] === 'filterTitle'),
        20,
        2
    );

    $registry->register(FilterSubscriber::class);
});

it('resolves the subscriber from the container so its dependencies inject', function () {
    $container = new Container();
    $instance = new ActionSubscriber();
    $container->instance(ActionSubscriber::class, $instance);
    $registry = new HookRegistry($container);

    Functions\expect('add_filter')->once()->with(
        'init',
        \Mockery::on(fn ($callback) => $callback[0] === $instance),
        10,
        1
    );

    $registry->register(ActionSubscriber::class);
});

it('does not wire the same subscriber hook twice', function () {
    $registry = new HookRegistry(new Container());

    Functions\expect('add_filter')->once();

    $registry->register(ActionSubscriber::class);
    $registry->register(ActionSubscriber::class);
});
