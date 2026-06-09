<?php

/**
 * Unit tests for the shared event seam (spec US2: FR-006, FR-007, FR-012a, SC-003, SC-008).
 *
 * @package Corex\Tests\Unit\Events
 */

declare(strict_types=1);

use Corex\Events\Event;
use Corex\Events\EventDispatcher;
use Corex\Events\ListenerProvider;
use Corex\Support\BootLogger;

final class OrderPlacedEvent implements Event
{
}

final class CartEmptiedEvent implements Event
{
}

function dispatcher(ListenerProvider $provider): EventDispatcher
{
    return new EventDispatcher($provider, new BootLogger(debug: false));
}

it('invokes every listener once each, in registration order', function () {
    $calls    = [];
    $provider = new ListenerProvider();
    $provider->listen(OrderPlacedEvent::class, function () use (&$calls): void {
        $calls[] = 'a';
    });
    $provider->listen(OrderPlacedEvent::class, function () use (&$calls): void {
        $calls[] = 'b';
    });
    $provider->listen(OrderPlacedEvent::class, function () use (&$calls): void {
        $calls[] = 'c';
    });

    $event = dispatcher($provider)->dispatch(new OrderPlacedEvent());

    expect($calls)->toBe(['a', 'b', 'c'])
        ->and($event)->toBeInstanceOf(OrderPlacedEvent::class);
});

it('does not invoke listeners registered for a different event type', function () {
    $ran      = false;
    $provider = new ListenerProvider();
    $provider->listen(CartEmptiedEvent::class, function () use (&$ran): void {
        $ran = true;
    });

    dispatcher($provider)->dispatch(new OrderPlacedEvent());

    expect($ran)->toBeFalse();
});

it('is a no-op when no listener is registered for the event', function () {
    $event = dispatcher(new ListenerProvider())->dispatch(new OrderPlacedEvent());

    expect($event)->toBeInstanceOf(OrderPlacedEvent::class);
});

it('logs a throwing listener and still runs the rest (best-effort)', function () {
    $calls    = [];
    $logger   = new BootLogger(debug: false);
    $provider = new ListenerProvider();
    $provider->listen(OrderPlacedEvent::class, function () use (&$calls): void {
        $calls[] = 'a';
    });
    $provider->listen(OrderPlacedEvent::class, function (): void {
        throw new RuntimeException('listener boom');
    });
    $provider->listen(OrderPlacedEvent::class, function () use (&$calls): void {
        $calls[] = 'c';
    });

    (new EventDispatcher($provider, $logger))->dispatch(new OrderPlacedEvent());

    expect($calls)->toBe(['a', 'c'])
        ->and($logger->messages())->toHaveCount(1)
        ->and($logger->messages()[0]['level'])->toBe('error');
});
