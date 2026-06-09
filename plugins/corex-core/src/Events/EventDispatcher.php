<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Events;

defined('ABSPATH') || exit;

use Corex\Support\BootLogger;
use Throwable;

/**
 * Dispatches an event to every listener registered for its class, once each, in
 * registration order. Dispatch is best-effort: a throwing listener is caught and
 * logged, and the remaining listeners still run (spec FR-012a, SC-008). The shared
 * seam lives in corex-core so any module — forms, Corex Mail — reuses it.
 */
final class EventDispatcher
{
    public function __construct(
        private readonly ListenerProvider $provider,
        private readonly BootLogger $logger,
    ) {
    }

    public function dispatch(object $event): object
    {
        foreach ($this->provider->listenersFor($event) as $listener) {
            try {
                $listener($event);
            } catch (Throwable $e) {
                $this->logger->error(sprintf('Event listener for %s failed: %s', $event::class, $e->getMessage()));
            }
        }

        return $event;
    }
}
