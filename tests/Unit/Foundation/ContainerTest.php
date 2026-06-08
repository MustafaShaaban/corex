<?php

/**
 * Unit tests for the DI container (spec US1: FR-005–FR-010, FR-007a).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Container\Container;
use Corex\Container\Exceptions\BindingResolutionException;
use Corex\Container\Exceptions\CircularDependencyException;
use Corex\Tests\Fixtures\Container\CycleA;
use Corex\Tests\Fixtures\Container\Greeter;
use Corex\Tests\Fixtures\Container\HasOptionalScalar;
use Corex\Tests\Fixtures\Container\Mailer;
use Corex\Tests\Fixtures\Container\MailerContract;
use Corex\Tests\Fixtures\Container\NeedsScalar;

require_once __DIR__ . '/ContainerFixtures.php';

it('returns the same instance for a singleton binding', function () {
    $container = new Container();
    $container->singleton(Mailer::class);

    expect($container->make(Mailer::class))->toBe($container->make(Mailer::class));
});

it('returns a new instance for a transient binding', function () {
    $container = new Container();
    $container->bind(Mailer::class);

    expect($container->make(Mailer::class))->not->toBe($container->make(Mailer::class));
});

it('autowires constructor dependencies', function () {
    $container = new Container();

    $greeter = $container->make(Greeter::class);

    expect($greeter)->toBeInstanceOf(Greeter::class)
        ->and($greeter->mailer)->toBeInstanceOf(Mailer::class);
});

it('returns a registered instance as a shared binding', function () {
    $container = new Container();
    $mailer = new Mailer();
    $container->instance(Mailer::class, $mailer);

    expect($container->make(Mailer::class))->toBe($mailer);
});

it('throws naming the interface when an interface dependency is unbound', function () {
    $container = new Container();

    expect(fn () => $container->make(MailerContract::class))
        ->toThrow(BindingResolutionException::class, MailerContract::class);
});

it('throws naming the class and parameter for an unresolvable scalar', function () {
    $container = new Container();

    $message = null;
    try {
        $container->make(NeedsScalar::class);
    } catch (BindingResolutionException $e) {
        $message = $e->getMessage();
    }

    expect($message)->toContain('NeedsScalar')->and($message)->toContain('name');
});

it('uses a default value for an optional constructor parameter', function () {
    $container = new Container();

    expect($container->make(HasOptionalScalar::class)->count)->toBe(5);
});

it('overrides autowiring with explicitly passed parameters', function () {
    $container = new Container();

    $object = $container->make(NeedsScalar::class, ['name' => 'Mustafa']);

    expect($object->name)->toBe('Mustafa');
});

it('detects a circular dependency instead of recursing forever', function () {
    $container = new Container();

    expect(fn () => $container->make(CycleA::class))
        ->toThrow(CircularDependencyException::class);
});

it('reports has() true for an existing class and false for an unbound interface', function () {
    $container = new Container();

    expect($container->has(Mailer::class))->toBeTrue()
        ->and($container->has(MailerContract::class))->toBeFalse();
});

it('resolves get() through make() per PSR-11', function () {
    $container = new Container();
    $container->singleton(Mailer::class);

    expect($container->get(Mailer::class))->toBe($container->make(Mailer::class));
});
