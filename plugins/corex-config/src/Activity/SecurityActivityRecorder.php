<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Activity;

defined('ABSPATH') || exit;

use Corex\Access\AccessUserDirectory;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Operations\OperationsMode;
use Corex\Events\ListenerProvider;
use Corex\Security\LoginLockoutEvent;
use DateTimeImmutable;

/**
 * Writes the security events the activity log was missing (spec 114, US-SAFE-13, FR-014).
 *
 * Mode changes, refusals of CoreX screens and sign-in lockouts were kept only in their own logs,
 * so the Insights card that reads the activity log said "No security events recorded" on a site
 * that had them. Each now adds one activity event:
 *
 * - `operations.mode.changed` (operations), when the mode store records a real change, from any
 *   route: the screen, the command line, a production launch;
 * - `access.denied` (access), when the access log opens a new counted entry: a page reloaded a
 *   thousand times in an hour is one entry there (spec 112) and one event here;
 * - `security.login.locked` (security), when login protection locks a sign-in out. It keeps when
 *   the lock ends, not the username that was typed nor the visitor's address.
 *
 * Access-request decisions were already written by the access service.
 *
 * Each write is the record of something another part of CoreX has already done, so one that
 * fails is let go: a mode change or a refusal never fails because its record could not be kept.
 */
final class SecurityActivityRecorder
{
    public const MODE_CHANGED  = 'operations.mode.changed';
    public const ACCESS_DENIED = 'access.denied';
    public const LOGIN_LOCKED  = 'security.login.locked';

    private const RETENTION = '+180 days';

    public function __construct(
        private readonly ActivityService $activity,
        private readonly AccessUserDirectory $users,
        private readonly ListenerProvider $listeners,
        private readonly SecurityEventsSince $since,
        private readonly OperationsMode $modes,
    ) {
    }

    public function register(): void
    {
        add_action('corex_operations_mode_changed', [$this, 'modeChanged'], 10, 3);
        add_action('corex_access_refusal_counted', [$this, 'refused'], 10, 4);
        $this->listeners->listen(LoginLockoutEvent::class, function (object $event): void {
            if ($event instanceof LoginLockoutEvent) {
                $this->lockedOut($event);
            }
        });
    }

    public function modeChanged(string $from, string $to, int $userId): void
    {
        $this->write(
            actorId: $userId,
            area: ActivityEvent::AREA_OPERATIONS,
            kind: self::MODE_CHANGED,
            target: ['mode', $to, $this->modeLabel($to)],
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            context: ['from' => $from, 'to' => $to],
        );
    }

    public function refused(int $userId, string $kind, string $section, int $at): void
    {
        $this->write(
            actorId: $userId,
            area: ActivityEvent::AREA_ACCESS,
            kind: self::ACCESS_DENIED,
            target: ['screen', $section, $section],
            outcome: ActivityEvent::OUTCOME_DENIED,
            context: ['refusal' => $kind],
            at: (new DateTimeImmutable())->setTimestamp($at),
        );
    }

    public function lockedOut(LoginLockoutEvent $event): void
    {
        $this->write(
            actorId: 0,
            area: ActivityEvent::AREA_SECURITY,
            kind: self::LOGIN_LOCKED,
            target: ['login', 'sign-in', __('Sign-in', 'corex')],
            outcome: ActivityEvent::OUTCOME_DENIED,
            context: ['locked_until' => $event->lockedUntil->format(DATE_ATOM)],
        );
    }

    /**
     * @param array{0:string,1:string,2:string} $target type, id and label
     * @param array<string,mixed>               $context
     */
    private function write(
        int $actorId,
        string $area,
        string $kind,
        array $target,
        string $outcome,
        array $context,
        ?DateTimeImmutable $at = null,
    ): void {
        $at = $at ?? new DateTimeImmutable('now');

        try {
            $this->since->mark($at->getTimestamp());
            $this->record($actorId, $area, $kind, $target, $outcome, $context, $at);
        } catch (\Throwable) {
            // Let go: see the class comment.
        }
    }

    /**
     * @param array{0:string,1:string,2:string} $target
     * @param array<string,mixed>               $context
     */
    private function record(int $actorId, string $area, string $kind, array $target, string $outcome, array $context, DateTimeImmutable $at): void
    {
        $this->activity->record(
            actorId: $actorId,
            actorKind: $actorId > 0 ? ActivityEvent::ACTOR_USER : ActivityEvent::ACTOR_SYSTEM,
            actorLabel: $actorId > 0 ? $this->users->displayName($actorId) : 'CoreX',
            area: $area,
            kind: $kind,
            targetType: $target[0],
            targetId: $target[1],
            targetLabel: $target[2],
            outcome: $outcome,
            summary: ['key' => $kind, 'args' => $context],
            context: $context,
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: $at->modify(self::RETENTION),
            occurredAt: $at,
        );
    }

    private function modeLabel(string $mode): string
    {
        return $this->modes->describe($mode)['label'];
    }
}
