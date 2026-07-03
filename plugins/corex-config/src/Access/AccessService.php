<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessChangePreview;
use Corex\Access\AccessPolicy;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RoleAbilityStore;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Operations\Confirmation;
use Corex\Operations\OperationResult;
use Corex\Support\Uuid;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Orchestrates previewed role changes and terminal access-request decisions.
 */
final class AccessService
{
    public const ROLE_CHANGE_OPERATION = 'access.role.change';

    public function __construct(
        private readonly CorexAbilityCatalog $catalog,
        private readonly AccessPolicy $policy,
        private readonly RoleAbilityStore $roleAbilities,
        private readonly AccessRequestStore $requests,
        private readonly AccessUserDirectory $users,
        private readonly ActivityService $activity,
    ) {
    }

    /** @param array<string,string> $changes */
    public function previewRoleChanges(int $actorId, string $roleKey, array $changes): AccessChangePreview
    {
        $this->assertRole($roleKey);

        return $this->policy->preview(
            actorId: $actorId,
            changes: $changes,
            affectedUserIds: $this->users->userIdsForRole($roleKey),
            fullAccessAdminIds: $this->users->fullAccessAdministratorIds(),
        );
    }

    /** @param array<string,string> $changes */
    public function roleChangeHash(string $roleKey, array $changes): string
    {
        $this->assertRole($roleKey);
        ksort($changes);

        return hash('sha256', json_encode([
            'role'    => $roleKey,
            'changes' => $changes,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string,string> $changes */
    public function changeRoleAbilities(
        int $actorId,
        string $roleKey,
        array $changes,
        Confirmation $confirmation,
        DateTimeImmutable $now,
    ): OperationResult {
        $preview = $this->previewRoleChanges($actorId, $roleKey, $changes);

        if (! $preview->allowed) {
            return $this->blocked(
                'access_change_blocked',
                __('The role ability change was blocked by the access safety policy.', 'corex'),
                $now,
                $preview->blockers,
            );
        }

        if (! $confirmation->verify(
            self::ROLE_CHANGE_OPERATION,
            $this->roleChangeHash($roleKey, $changes),
            $actorId,
            null,
            $now,
        )) {
            return $this->blocked(
                'confirmation_invalid',
                __('The role ability confirmation is invalid or expired.', 'corex'),
                $now,
            );
        }

        $oldEffects = $this->roleAbilities->effectsForRole($roleKey);
        $this->roleAbilities->apply($roleKey, $changes, $actorId, $now);

        $event = $this->activity->record(
            actorId: $actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: $this->users->displayName($actorId),
            area: ActivityEvent::AREA_ACCESS,
            kind: 'access.role.changed',
            targetType: 'role',
            targetId: $roleKey,
            targetLabel: $roleKey,
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'access.role.changed', 'args' => ['role' => $roleKey]],
            context: ['old_effects' => $oldEffects, 'new_effects' => $changes],
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: $now->modify('+180 days'),
            occurredAt: $now,
        );

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_COMPLETED,
            message: __('The role abilities were updated.', 'corex'),
            errors: [],
            affectedIds: $this->users->userIdsForRole($roleKey),
            startedAt: $now,
            finishedAt: $now,
            auditEventId: $event->id,
        );
    }

    public function requestAccess(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): OperationResult {
        $this->assertRequest($requesterId, $abilityKey, $areaKey, $reason, $now, $expiresAt);
        $requestId = $this->requests->create(
            $requesterId,
            $abilityKey,
            $areaKey,
            trim($reason),
            $now,
            $expiresAt,
        );

        $event = $this->activity->record(
            actorId: $requesterId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: $this->users->displayName($requesterId),
            area: ActivityEvent::AREA_ACCESS,
            kind: 'access.request.created',
            targetType: 'access_request',
            targetId: (string) $requestId,
            targetLabel: sprintf(
                /* translators: %d: access request ID. */
                __('Access request %d', 'corex'),
                $requestId,
            ),
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'access.request.created', 'args' => ['request' => $requestId]],
            context: ['request_id' => $requestId, 'ability_key' => $abilityKey, 'area_key' => $areaKey],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->modify('+180 days'),
            occurredAt: $now,
        );

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_COMPLETED,
            message: __('The access request was created.', 'corex'),
            errors: [],
            affectedIds: [$requestId],
            startedAt: $now,
            finishedAt: $now,
            auditEventId: $event->id,
        );
    }

    public function decideRequest(
        int $actorId,
        int $requestId,
        bool $approved,
        string $note,
        DateTimeImmutable $now,
    ): OperationResult {
        $request = $this->requests->find($requestId);

        if ($request === null || ($request['state'] ?? null) !== 'pending') {
            return $this->blocked('request_not_pending', __('The access request is no longer pending.', 'corex'), $now);
        }

        if (($request['expiresAt'] ?? null) instanceof DateTimeImmutable && $request['expiresAt'] <= $now) {
            $this->requests->transition($requestId, 'expired', $actorId, $note, $now);

            return $this->blocked('request_expired', __('The access request has expired.', 'corex'), $now);
        }

        $state = $approved ? 'approved' : 'denied';
        if (! $this->requests->transition($requestId, $state, $actorId, trim($note), $now)) {
            return $this->blocked('request_not_pending', __('The access request is no longer pending.', 'corex'), $now);
        }

        if ($approved) {
            foreach ($this->requestedAbilities($request) as $abilityKey) {
                $this->users->grantUserAbility((int) $request['requesterId'], $abilityKey);
            }
        }

        $kind  = 'access.request.' . $state;
        $event = $this->activity->record(
            actorId: $actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: $this->users->displayName($actorId),
            area: ActivityEvent::AREA_ACCESS,
            kind: $kind,
            targetType: 'access_request',
            targetId: (string) $requestId,
            targetLabel: sprintf(
                /* translators: %d: access request ID. */
                __('Access request %d', 'corex'),
                $requestId,
            ),
            outcome: $approved ? ActivityEvent::OUTCOME_SUCCESS : ActivityEvent::OUTCOME_DENIED,
            summary: ['key' => $kind, 'args' => ['request' => $requestId]],
            context: ['requester_id' => (int) $request['requesterId'], 'decision' => $state],
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: $now->modify('+180 days'),
            occurredAt: $now,
        );

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_COMPLETED,
            message: $approved
                ? __('The access request was approved.', 'corex')
                : __('The access request was denied.', 'corex'),
            errors: [],
            affectedIds: [$requestId],
            startedAt: $now,
            finishedAt: $now,
            auditEventId: $event->id,
        );
    }

    /** @param list<array{code:string,ability:string}> $blockers */
    private function blocked(
        string $code,
        string $message,
        DateTimeImmutable $now,
        array $blockers = [],
    ): OperationResult {
        $errors = $blockers === []
            ? [['code' => $code, 'message' => $message]]
            : array_map(
                static fn (array $blocker): array => [
                    'code'    => $blocker['code'],
                    'message' => sprintf(
                        /* translators: %s: CoreX ability key. */
                        __('Ability %s cannot be changed.', 'corex'),
                        $blocker['ability'],
                    ),
                ],
                $blockers,
            );

        return new OperationResult(
            operationId: Uuid::v4(),
            state: OperationResult::STATE_BLOCKED,
            message: $message,
            errors: $errors,
            affectedIds: [],
            startedAt: $now,
            finishedAt: $now,
        );
    }

    /** @param array<string,mixed> $request @return list<string> */
    private function requestedAbilities(array $request): array
    {
        if (is_string($request['abilityKey'] ?? null)) {
            return [(string) $request['abilityKey']];
        }

        $area = (string) ($request['areaKey'] ?? '');

        return array_map(
            static fn (CorexAbility $ability): string => $ability->key,
            $this->catalog->grouped()[$area] ?? [],
        );
    }

    private function assertRequest(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): void {
        if ($requesterId < 1 || trim($reason) === '' || mb_strlen($reason) > 2000 || $expiresAt <= $now) {
            throw new InvalidArgumentException('Access request details are invalid.');
        }

        if (($abilityKey === null) === ($areaKey === null)) {
            throw new InvalidArgumentException('An access request must target exactly one ability or area.');
        }

        if ($abilityKey !== null && $this->catalog->find($abilityKey) === null) {
            throw new InvalidArgumentException('The requested CoreX ability is unknown.');
        }

        if ($areaKey !== null && ! array_key_exists($areaKey, $this->catalog->grouped())) {
            throw new InvalidArgumentException('The requested CoreX area is unknown.');
        }
    }

    private function assertRole(string $roleKey): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $roleKey) !== 1) {
            throw new InvalidArgumentException('Role key is invalid.');
        }
    }
}
