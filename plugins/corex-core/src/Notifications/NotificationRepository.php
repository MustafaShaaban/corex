<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Persistence for notifications and per-user state. Every actor-scoped read takes the actor id and a
 * capability check, and returns only what the actor may see (spec 072 FR-002/FR-003) — visibility is
 * never optional. The WP adapter clamps page size, prepares every query, and prunes in bounded
 * batches, mirroring {@see \Corex\Config\Activity\WpActivityRepository}.
 */
interface NotificationRepository
{
    /**
     * Insert, or if an unresolved notification with the same dedup key exists, record another
     * occurrence and return the merged record (spec 072 FR-011).
     */
    public function upsertByDedupKey(Notification $notification): Notification;

    public function find(int $id): ?Notification;

    /**
     * A bounded, visibility-filtered page for the actor.
     *
     * @param callable(string):bool $userCan
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function queryForActor(NotificationQuery $query, int $actorId, callable $userCan): array;

    /** @param callable(string):bool $userCan */
    public function unreadCountForActor(int $actorId, callable $userCan): int;

    /** Per-user state mutations. Each asserts the actor may see the notification first. */
    public function markRead(int $notificationId, int $actorId): bool;

    public function markUnread(int $notificationId, int $actorId): bool;

    public function markAllVisibleRead(int $actorId, callable $userCan): int;

    public function dismiss(int $notificationId, int $actorId): bool;

    public function snooze(int $notificationId, int $actorId, DateTimeImmutable $until): bool;

    /** Condition lifecycle — resolve/reopen by dedup key, independent of any user's dismissal. */
    public function resolveByDedupKey(string $dedupKey, string $reason, DateTimeImmutable $at): int;

    public function reopenByDedupKey(string $dedupKey): int;

    /** Bounded prune of expired/resolved notifications older than the cutoff; returns the count removed. */
    public function pruneOlderThan(DateTimeImmutable $cutoff, int $limit = 500): int;
}
