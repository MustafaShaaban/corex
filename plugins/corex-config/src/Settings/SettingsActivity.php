<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Access\AccessUserDirectory;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use DateTimeImmutable;

/**
 * Settings changes the activity log keeps (spec 119, US-SET-15): a removed secret, by whom, named by
 * its setting and never by its value.
 */
final class SettingsActivity
{
    private const RETENTION = '+180 days';

    public function __construct(
        private readonly ActivityService $activity,
        private readonly AccessUserDirectory $users,
    ) {
    }

    public function secretRemoved(int $actorId, string $key, string $label): void
    {
        $now = new DateTimeImmutable('now');

        try {
            $this->activity->record(
                actorId: $actorId,
                actorKind: ActivityEvent::ACTOR_USER,
                actorLabel: $this->users->displayName($actorId),
                area: ActivityEvent::AREA_SETTINGS,
                kind: 'settings.secret_removed',
                targetType: 'setting',
                targetId: $key,
                targetLabel: $label,
                outcome: ActivityEvent::OUTCOME_SUCCESS,
                summary: ['key' => 'settings.secret_removed', 'args' => ['setting' => $key]],
                context: [],
                sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
                retentionUntil: $now->modify(self::RETENTION),
                occurredAt: $now,
            );
        } catch (\Throwable) {
            // The secret is already gone; a record that cannot be kept does not undo that.
        }
    }
}
