<?php

/**
 * Shared test double for {@see NotificationService} that records every published notification and
 * treats the current-actor operations as inert. Producer tests only care about what gets published,
 * so the read/mutation surface returns harmless defaults.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Notifications\Notification;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;
use DateTimeImmutable;

final class RecordingNotificationService implements NotificationService
{
    /** @var list<Notification> */
    public array $published = [];

    public function publish(Notification $notification): Notification
    {
        $this->published[] = $notification;

        return $notification->withId(count($this->published));
    }

    public function resolve(string $dedupKey, string $reason): int
    {
        return 0;
    }

    public function forCurrentActor(NotificationQuery $query): array
    {
        return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20];
    }

    public function unreadCountForCurrentActor(): int
    {
        return 0;
    }

    public function findForCurrentActor(int $notificationId): ?array
    {
        return null;
    }

    public function markReadForCurrentActor(int $notificationId): bool
    {
        return false;
    }

    public function markUnreadForCurrentActor(int $notificationId): bool
    {
        return false;
    }

    public function dismissForCurrentActor(int $notificationId): bool
    {
        return false;
    }

    public function snoozeForCurrentActor(int $notificationId, DateTimeImmutable $until): bool
    {
        return false;
    }

    public function markAllReadForCurrentActor(): int
    {
        return 0;
    }

    public function resolveById(int $notificationId, string $reason): bool
    {
        return false;
    }
}
