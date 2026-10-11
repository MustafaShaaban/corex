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
use Throwable;

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
    public const INSTALLED = 'installed';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';
    public const RETURNED  = 'returned';

    private const OUTCOMES = [
        self::INSTALLED => ActivityEvent::OUTCOME_SUCCESS,
        self::FAILED    => ActivityEvent::OUTCOME_FAILURE,
        self::CANCELLED => ActivityEvent::OUTCOME_WARNING,
        self::RETURNED  => ActivityEvent::OUTCOME_REVERTED,
    ];

    /**
     * @param Closure(int):string $nameOf The name an administrator goes by, for the activity log.
     */
    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ActivityService $activity,
        private readonly Closure $nameOf,
    ) {
    }

    /**
     * @param string               $event   One of this class's four.
     * @param string               $version The release it is about.
     * @param string|null          $from    The release the site ran before, where it knew.
     * @param array<string,scalar> $more    What else is worth keeping: a reason, a package's name. Never a key.
     */
    public function write(string $event, string $version, ?string $from, int $actorId, DateTimeImmutable $at, array $more = []): void
    {
        $this->store->record([
            'event'   => $event,
            'version' => $version,
            'from'    => $from,
            'by'      => $actorId,
            'at'      => $at->format(DATE_ATOM),
            ...$more,
        ]);

        try {
            $this->activity->record(
                actorId: $actorId,
                actorKind: ActivityEvent::ACTOR_USER,
                actorLabel: ($this->nameOf)($actorId),
                area: ActivityEvent::AREA_OPERATIONS,
                kind: 'release.' . $event,
                targetType: 'release',
                targetId: $version,
                targetLabel: $version,
                outcome: self::OUTCOMES[$event],
                summary: ['key' => 'release.' . $event, 'args' => ['from' => (string) $from, 'to' => $version]],
                context: $more,
                sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
                retentionUntil: $at->modify('+365 days'),
                occurredAt: $at,
            );
        } catch (Throwable) {
            // The activity log is a table of the release being installed. When it cannot take
            // an entry (its own change is the one that failed), the installer's log has it.
        }
    }
}
