<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Notification;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Profile\Support\SiteDate;

/**
 * Pure mapper from a core {@see ActivityEvent} to a front-office notification row.
 * Front-office notifications are a permission-safe projection of the signed-in user's
 * own activity — no separate, invented store — so they are always truthful.
 *
 * `label` and `when` are what the account block prints: a sentence, and the instant as
 * the site writes a date. The other keys are the event as a program reads it.
 */
final class NotificationList
{
    /**
     * @return array{label:string,when:string,area:string,kind:string,target:string,outcome:string,occurredAt:string,summaryKey:string}
     */
    public static function fromEvent(ActivityEvent $event, SiteDate $dates): array
    {
        return [
            'label'      => AccountActivity::sentence($event->kind),
            'when'       => $dates->dateAndTime($event->occurredAt->getTimestamp()),
            'area'       => $event->area,
            'kind'       => $event->kind,
            'target'     => $event->targetLabel,
            'outcome'    => $event->outcome,
            'occurredAt' => $event->occurredAt->format(DATE_ATOM),
            'summaryKey' => (string) ($event->summary['key'] ?? ''),
        ];
    }
}
