<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * The dependency-aware boot seam for notification producers. Modules contribute a
 * {@see NotificationProducer}; at boot the registry wires up only those whose module is present
 * (spec 072 FR-014) — an absent module produces nothing, never fabricated data — and wires each one
 * at most once however often boot runs. Producers hook their own events; the registry only decides
 * which ones get to.
 */
final class NotificationProducerRegistry
{
    /** @var list<NotificationProducer> */
    private array $producers = [];

    /** @var list<string> keys of producers already registered, in registration order */
    private array $registered = [];

    public function add(NotificationProducer $producer): void
    {
        $this->producers[] = $producer;
    }

    /** Wire every available, not-yet-registered producer. Safe to call more than once. */
    public function register(): void
    {
        foreach ($this->producers as $producer) {
            if (! $producer->isAvailable() || in_array($producer->key(), $this->registered, true)) {
                continue;
            }

            $producer->register();
            $this->registered[] = $producer->key();
        }
    }

    /** @return list<string> */
    public function registeredKeys(): array
    {
        return $this->registered;
    }
}
