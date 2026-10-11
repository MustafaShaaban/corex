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
        ReleaseEvent::RETURNED  => ActivityEvent::OUTCOME_REVERTED,
    ];

    /** How many of the events the screen shows. */
    private const SHOWN = 20;

    /**
     * @param Closure(int):string $nameOf The name an administrator goes by, for the activity log. Never empty.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ActivityService $activity,
        private readonly Closure $nameOf,
    ) {
    }

    /**
     * What happened here, newest first, each with the name of who did it.
     *
     * @return list<array<string,mixed>> `by_name` is empty for an event nobody is known to have made: a return through the recovery link.
     */
    public function entries(): array
    {
        return array_map(
            fn (array $event): array => [...$event, 'by_name' => (int) ($event['by'] ?? 0) > 0 ? ($this->nameOf)((int) $event['by']) : ''],
            array_slice(array_reverse($this->store->log()), 0, self::SHOWN),
        );
    }

    /**
     * @param int $actorId Who did it; 0 when nobody is known to have: the recovery link asks for a key, not a name.
     */
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
                actorKind: $actorId > 0 ? ActivityEvent::ACTOR_USER : ActivityEvent::ACTOR_SYSTEM,
                actorLabel: $actorId > 0 ? ($this->nameOf)($actorId) : __('The recovery link', 'corex'),
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
