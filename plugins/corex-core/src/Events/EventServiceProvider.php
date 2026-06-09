<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Events;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\BootLogger;

/**
 * Binds the shared event seam (ListenerProvider + EventDispatcher) as singletons so
 * every module registers listeners on, and dispatches through, the same instances.
 */
final class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ListenerProvider::class);

        $this->container->singleton(
            EventDispatcher::class,
            static fn (ContainerInterface $c): EventDispatcher => new EventDispatcher(
                $c->make(ListenerProvider::class),
                $c->make(BootLogger::class),
            ),
        );
    }
}
