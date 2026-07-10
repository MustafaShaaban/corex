<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Notification;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;

/**
 * Pure mapper from a core {@see ActivityEvent} to a front-office notification row.
 * Front-office notifications are a permission-safe projection of the signed-in user's
 * own activity — no separate, invented store — so they are always truthful.
 */
final class NotificationList
{
    /**
     * @return array{area:string,kind:string,target:string,outcome:string,occurredAt:string,summaryKey:string}
     */
    public static function fromEvent(ActivityEvent $event): array
    {
        return [
            'area'       => $event->area,
            'kind'       => $event->kind,
            'target'     => $event->targetLabel,
            'outcome'    => $event->outcome,
            'occurredAt' => $event->occurredAt->format(DATE_ATOM),
            'summaryKey' => (string) ($event->summary['key'] ?? ''),
        ];
    }
}
