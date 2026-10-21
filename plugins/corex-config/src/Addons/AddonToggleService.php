<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Multisite\ActivationScope;
use DateTimeImmutable;

/**
 * Turns an add-on on or off for this site and says what happened (spec 114, US-SAFE-08).
 *
 * An add-on active for the whole network, or installed as must-use, is not this site's to
 * switch off: disabling one is refused here, whatever the request asked. Every attempt, the
 * refused and the failed among them, is written to the activity log with who asked and what
 * came of it.
 */
final class AddonToggleService
{
    private const RETENTION = '+180 days';

    public function __construct(
        private readonly AddonActivator $activator,
        private readonly ActivityService $activity,
    ) {
    }

    /** Why this site cannot switch the add-on off, or '' when it can. */
    public static function lockedReason(ActivationScope $scope): string
    {
        return match ($scope) {
            ActivationScope::Network => __('Managed by the network administrator', 'corex'),
            ActivationScope::MustUse => __('Must-use: always on', 'corex'),
            default                  => '',
        };
    }

    public function enable(Addon $addon, int $actorId): AddonToggleResult
    {
        $result = $this->activator->enable($addon);
        $this->record('addon.enabled', $addon, $actorId, $result);

        return $result;
    }

    public function disable(Addon $addon, ActivationScope $scope, int $actorId): AddonToggleResult
    {
        $locked = self::lockedReason($scope);
        $result = $locked === ''
            ? $this->activator->disable($addon)
            : AddonToggleResult::failed($locked);
        $this->record('addon.disabled', $addon, $actorId, $result);

        return $result;
    }

    private function record(string $kind, Addon $addon, int $actorId, AddonToggleResult $result): void
    {
        $now  = new DateTimeImmutable('now');
        $user = get_userdata($actorId);

        $this->activity->record(
            actorId: $actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: $user instanceof \WP_User ? $user->display_name : '',
            area: ActivityEvent::AREA_ADDONS,
            kind: $kind,
            targetType: 'addon',
            targetId: $addon->slug,
            targetLabel: $addon->label,
            outcome: $result->succeeded ? ActivityEvent::OUTCOME_SUCCESS : ActivityEvent::OUTCOME_FAILURE,
            summary: ['key' => $kind, 'args' => ['addon' => $addon->slug]],
            context: $result->succeeded ? [] : ['reason' => $result->reason],
            sensitivity: ActivityEvent::SENSITIVITY_PUBLIC_ADMIN,
            retentionUntil: $now->modify(self::RETENTION),
            occurredAt: $now,
        );
    }
}
