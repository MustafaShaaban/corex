<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Events;

defined('ABSPATH') || exit;

/**
 * Holds listeners keyed by event class. Listeners are returned in the order they
 * were registered, so dispatch is deterministic (spec FR-006, FR-007).
 */
final class ListenerProvider
{
    /**
     * @var array<class-string,list<callable>>
     */
    private array $listeners = [];

    /**
     * @param callable(object):void $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * @return list<callable> listeners for the event's class, in registration order
     */
    public function listenersFor(object $event): array
    {
        return $this->listeners[$event::class] ?? [];
    }
}
