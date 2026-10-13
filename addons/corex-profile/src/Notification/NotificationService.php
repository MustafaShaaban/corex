<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Notification;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Profile\Support\SiteDate;

/**
 * Front-office notifications for the signed-in user, projected from the shared core
 * activity stream filtered to that user as actor. Read-scoped to the current user, so
 * one account can never see another's notifications. Thin: the mapping is pure
 * {@see NotificationList}; the query is the core {@see ActivityService}.
 *
 * Only the kinds {@see AccountActivity} has a sentence for are listed. The stream also
 * holds what the same person did elsewhere in the product, which the add-on cannot word,
 * and an administrator does more of that than of this (spec 068, FR-161g).
 */
final class NotificationService
{
    public function __construct(
        private readonly ActivityService $activity,
        private readonly SiteDate $dates,
    ) {
    }

    /**
     * @return list<array{label:string,when:string,area:string,kind:string,target:string,outcome:string,occurredAt:string,summaryKey:string}>
     */
    public function forUser(int $userId, int $limit = 10): array
    {
        if ($userId <= 0 || $userId !== get_current_user_id()) {
            return [];
        }

        return array_map(
            fn (ActivityEvent $event): array => NotificationList::fromEvent($event, $this->dates),
            $this->newest($userId, max(1, min($limit, 50))),
        );
    }

    /**
     * The newest events of the listed kinds, whatever their kind.
     *
     * The stream is asked once for each kind, because it filters by one kind at a time: the
     * newest of each are enough to hold the newest of all of them. Asking for the person's
     * newest events and leaving out the others afterwards would lose a saved profile behind
     * ten exports.
     *
     * @return list<ActivityEvent>
     */
    private function newest(int $userId, int $limit): array
    {
        $events = [];
        foreach (AccountActivity::kinds() as $kind) {
            $events = [...$events, ...$this->activity->query(['actor_id' => $userId, 'kind' => $kind], 1, $limit)];
        }

        usort(
            $events,
            static fn (ActivityEvent $a, ActivityEvent $b): int => [$b->occurredAt, $b->id] <=> [$a->occurredAt, $a->id],
        );

        return array_slice($events, 0, $limit);
    }
}
