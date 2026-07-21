<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

use DateTimeImmutable;

/**
 * The boundary producers and UI program against. It owns publishing (with dedup + loop-safe channel
 * delivery) and actor-scoped reading, delegating storage to {@see NotificationRepository}. Modules
 * publish through this contract; the Center never reaches into a module's private tables (spec 072).
 *
 * Every `…ForCurrentActor` method resolves "the current actor" from WordPress and re-checks
 * visibility through the repository, so a caller can never read or mutate a notification it may not
 * see — the read/own-action tier of the REST gate rests on that (FR-002/FR-003).
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

    /** One notification presented for the current actor, or null if absent or not theirs to see. */
    public function findForCurrentActor(int $notificationId): ?array;

    /** Per-user state mutations for the current actor. Each returns false when it is not theirs. */
    public function markReadForCurrentActor(int $notificationId): bool;

    public function markUnreadForCurrentActor(int $notificationId): bool;

    public function dismissForCurrentActor(int $notificationId): bool;

    public function snoozeForCurrentActor(int $notificationId, DateTimeImmutable $until): bool;

    /** Mark every notification the current actor can see as read; returns the number affected. */
    public function markAllReadForCurrentActor(): int;

    /** Resolve the condition a notification belongs to (manage tier). False if the id is unknown. */
    public function resolveById(int $notificationId, string $reason): bool;
}
