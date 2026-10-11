<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Closure;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use DateTimeImmutable;
use RuntimeException;

/**
 * What happened to a site's release, written down twice (spec 107, US5; FR-051; plan D14).
 *
 * In the installer's own log, which is a file beside the releases: it has to hold when a
 * release's database change failed and the tables are in doubt. And in the activity log,
 * where an administrator looks for who did what, when it can take an entry.
 *
 * An installation's key is given to neither. What reaches this is versions, a reason and a
 * package's name.
 */
final class ReleaseRecord
{
    private const OUTCOMES = [
        ReleaseEvent::INSTALLED => ActivityEvent::OUTCOME_SUCCESS,
        ReleaseEvent::FAILED    => ActivityEvent::OUTCOME_FAILURE,
        ReleaseEvent::CANCELLED => ActivityEvent::OUTCOME_WARNING,
    ];

    /**
     * @param Closure(int):string $nameOf The name an administrator goes by, for the activity log. Never empty.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ActivityService $activity,
        private readonly Closure $nameOf,
    ) {
    }

    public function write(ReleaseEvent $event, int $actorId, DateTimeImmutable $at): void
    {
        $this->store->record([
            'event'   => $event->kind,
            'version' => $event->version,
            'from'    => $event->from,
            'by'      => $actorId,
            'at'      => $at->format(DATE_ATOM),
            ...$event->details,
        ]);

        try {
            $this->activity->record(
                actorId: $actorId,
                actorKind: ActivityEvent::ACTOR_USER,
                actorLabel: ($this->nameOf)($actorId),
                area: ActivityEvent::AREA_OPERATIONS,
                kind: 'release.' . $event->kind,
                targetType: 'release',
                targetId: $event->version,
                targetLabel: $event->version,
                outcome: self::OUTCOMES[$event->kind],
                summary: ['key' => 'release.' . $event->kind, 'args' => ['from' => (string) $event->from, 'to' => $event->version]],
                context: $event->details,
                sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
                retentionUntil: $at->modify('+365 days'),
                occurredAt: $at,
            );
        } catch (RuntimeException) {
            // What the activity log throws when its table will not take a row. That table is
            // one of the release being installed, so its own change may be the one that
            // failed. The installer's log, written above, has the event either way.
        }
    }
}
