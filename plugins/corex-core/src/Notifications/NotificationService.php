<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * The boundary producers and UI program against. It owns publishing (with dedup + loop-safe channel
 * delivery) and actor-scoped reading, delegating storage to {@see NotificationRepository}. Modules
 * publish through this contract; the Center never reaches into a module's private tables (spec 072).
 */
interface NotificationService
{
    /** Publish a notification (or record another occurrence of an existing condition). */
    public function publish(Notification $notification): Notification;

    /** Resolve a condition-based notification by its dedup key when the condition ends. */
    public function resolve(string $dedupKey, string $reason): int;

    /**
     * The current actor's bounded, visibility-filtered notifications.
     *
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function forCurrentActor(NotificationQuery $query): array;

    /** The current actor's unread count (bounded aggregate; safe to cache briefly). */
    public function unreadCountForCurrentActor(): int;
}
