<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * Turns a module's events into notifications. A producer registers itself only when its module is
 * present (spec 072 FR-014) — an absent module produces nothing, never fabricated data. Producers
 * hook the events they care about and call {@see NotificationService::publish()}; they never run
 * remote checks during rendering (FR-015).
 */
interface NotificationProducer
{
    /** A stable identifier for this producer (e.g. `forms.submissions`). */
    public function key(): string;

    /** Whether this producer's module is available in the current install. */
    public function isAvailable(): bool;

    /** Wire the producer's event subscriptions. Called once at boot when {@see isAvailable()}. */
    public function register(): void;
}
