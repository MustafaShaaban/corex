<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Notification;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityService;

/**
 * Front-office notifications for the signed-in user, projected from the shared core
 * activity stream filtered to that user as actor. Read-scoped to the current user, so
 * one account can never see another's notifications. Thin: the mapping is pure
 * {@see NotificationList}; the query is the core {@see ActivityService}.
 */
final class NotificationService
{
    public function __construct(private readonly ActivityService $activity)
    {
    }

    /**
     * @return list<array{area:string,kind:string,target:string,outcome:string,occurredAt:string,summaryKey:string}>
     */
    public function forUser(int $userId, int $limit = 10): array
    {
        if ($userId <= 0 || $userId !== get_current_user_id()) {
            return [];
        }

        $events = $this->activity->query(['actor_id' => $userId], 1, max(1, min($limit, 50)));

        return array_map(
            static fn ($event): array => NotificationList::fromEvent($event),
            $events,
        );
    }
}
