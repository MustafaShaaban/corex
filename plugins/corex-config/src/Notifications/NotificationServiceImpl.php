<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Notifications\Notification;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationRepository;
use Corex\Notifications\NotificationService;
use DateTimeImmutable;

/**
 * The WordPress-side boundary modules and UI program against. It resolves "the current actor" from
 * WordPress (`get_current_user_id`, `current_user_can`) and delegates storage + visibility to the
 * repository, so producers and controllers stay free of that plumbing. Publishing is dedup-aware
 * (via the repository) and never throws to a producer — a notification failure must not derail the
 * event that caused it.
 */
final class NotificationServiceImpl implements NotificationService
{
    public function __construct(private readonly NotificationRepository $repository)
    {
    }

    public function publish(Notification $notification): Notification
    {
        return $this->repository->upsertByDedupKey($notification);
    }

    public function resolve(string $dedupKey, string $reason): int
    {
        return $this->repository->resolveByDedupKey($dedupKey, $reason, new DateTimeImmutable('now'));
    }

    public function forCurrentActor(NotificationQuery $query): array
    {
        $actorId = get_current_user_id();
        if ($actorId < 1) {
            return ['items' => [], 'total' => 0, 'page' => $query->page, 'per_page' => $query->perPage];
        }

        return $this->repository->queryForActor($query, $actorId, $this->actorCan());
    }

    public function unreadCountForCurrentActor(): int
    {
        $actorId = get_current_user_id();
        if ($actorId < 1) {
            return 0;
        }

        return $this->repository->unreadCountForActor($actorId, $this->actorCan());
    }

    public function findForCurrentActor(int $notificationId): ?array
    {
        $actorId = get_current_user_id();
        if ($actorId < 1) {
            return null;
        }

        return $this->repository->findForActor($notificationId, $actorId, $this->actorCan());
    }

    public function markReadForCurrentActor(int $notificationId): bool
    {
        $actorId = get_current_user_id();

        return $actorId >= 1 && $this->repository->markRead($notificationId, $actorId);
    }

    public function markUnreadForCurrentActor(int $notificationId): bool
    {
        $actorId = get_current_user_id();

        return $actorId >= 1 && $this->repository->markUnread($notificationId, $actorId);
    }

    public function dismissForCurrentActor(int $notificationId): bool
    {
        $actorId = get_current_user_id();

        return $actorId >= 1 && $this->repository->dismiss($notificationId, $actorId);
    }

    public function snoozeForCurrentActor(int $notificationId, DateTimeImmutable $until): bool
    {
        $actorId = get_current_user_id();

        return $actorId >= 1 && $this->repository->snooze($notificationId, $actorId, $until);
    }

    public function markAllReadForCurrentActor(): int
    {
        $actorId = get_current_user_id();
        if ($actorId < 1) {
            return 0;
        }

        return $this->repository->markAllVisibleRead($actorId, $this->actorCan());
    }

    public function resolveById(int $notificationId, string $reason): bool
    {
        $notification = $this->repository->find($notificationId);
        if ($notification === null) {
            return false;
        }

        return $this->repository->resolveByDedupKey($notification->dedupKey, $reason, new DateTimeImmutable('now')) > 0;
    }

    /** @return callable(string):bool */
    private function actorCan(): callable
    {
        return static fn (string $ability): bool => current_user_can($ability);
    }
}
