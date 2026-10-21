<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessChangePreview;
use Corex\Access\AccessPolicy;
use Corex\Access\AccessRequestedEvent;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\InvalidAccessInput;
use Corex\Access\RequestableAbilities;
use Corex\Access\RoleAbilityStore;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Events\EventDispatcher;
use Corex\Operations\Confirmation;
use Corex\Operations\OperationResult;
use Corex\Mail\RoutedMailer;
use Corex\Support\Uuid;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Orchestrates previewed role changes and terminal access-request decisions.
 */
final class AccessService
{
    public const ROLE_CHANGE_OPERATION = 'access.role.change';

    /** What an approval's confirmation is for (spec 112, FR-002). */
    public const APPROVAL_OPERATION = 'access.request.approve';

    /** The expiries an approver chooses from, in days; 30 is offered first (spec 112, FR-002). */
    public const GRANT_EXPIRY_DAYS = [7, 30, 90];

    /** The longest reason a request is stored with, in characters. */
    public const MAX_REASON_LENGTH = 2000;

    public function __construct(
        private readonly CorexAbilityCatalog $catalog,
        private readonly AccessPolicy $policy,
        private readonly RoleAbilityStore $roleAbilities,
        private readonly AccessRequestStore $requests,
        private readonly AccessUserDirectory $users,
        private readonly ActivityService $activity,
        private readonly RequestableAbilities $requestable,
        private readonly ?RoutedMailer $notifications = null,
        private readonly ?EventDispatcher $events = null,
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

    /**
     * Files a request, unless this person is already waiting on one for the same ability or area.
     *
     * The second ask is refused here and not by a caller, so the REST route, the form in the admin
     * and anything written later give one answer: nothing is stored, nothing is audited as
     * created and nobody is notified again. A request for something else, from somebody else, or
     * made after the earlier one was decided or ran out, is a new request.
     */
    public function requestAccess(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $now,
        DateTimeImmutable $expiresAt,
    ): OperationResult {
        $this->assertRequest($requesterId, $abilityKey, $areaKey, $reason, $now, $expiresAt);
        $requestId = $this->requests->createUnlessPending(
            $requesterId,
            $abilityKey,
            $areaKey,
            trim($reason),
            $now,
            $expiresAt,
        );

        if ($requestId === null) {
            return $this->blocked(
                'request_already_pending',
                __('Your request has already been sent. An administrator has been notified and can approve or deny it.', 'corex'),
                $now,
            );
        }

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

        $this->notify('access.request.created', [
            'request' => [
                'id'          => $requestId,
                'ability_key' => $abilityKey,
                'area_key'    => $areaKey,
            ],
            'requester' => [
                'id'    => $requesterId,
                'name'  => $this->users->displayName($requesterId),
                'email' => $this->users->emailAddress($requesterId),
            ],
        ]);

        // Surface the pending request in the Notification Center, independently of the email channel.
        $this->events?->dispatch(new AccessRequestedEvent(
            requestId: $requestId,
            requesterId: $requesterId,
            requesterName: $this->users->displayName($requesterId),
            abilityKey: $abilityKey,
            areaKey: $areaKey,
        ));

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

    /**
     * What approving this request would grant, for the approver to read before confirming
     * (spec 112, FR-002, FR-003). Null when the request is no longer pending.
     */
    public function previewApproval(int $actorId, int $requestId): ?AccessApprovalPreview
    {
        $request = $this->requests->find($requestId);
        if ($request === null || ($request['state'] ?? null) !== 'pending') {
            return null;
        }

        $keys      = $this->requestedAbilities($request);
        $abilities = array_values(array_filter(array_map($this->catalog->find(...), $keys)));
        $policy    = $this->policy->preview(
            actorId: $actorId,
            changes: array_fill_keys($keys, AccessPolicy::EFFECT_ALLOW),
            affectedUserIds: [(int) $request['requesterId']],
            fullAccessAdminIds: $this->users->fullAccessAdministratorIds(),
        );

        return new AccessApprovalPreview(
            requestId: $requestId,
            requesterId: (int) $request['requesterId'],
            reason: (string) $request['reason'],
            areaKey: $request['areaKey'] ?? null,
            abilities: $abilities,
            blockers: $policy->blockers,
            typedName: $this->typedName($abilities),
            targetHash: $this->approvalHash($requestId, $keys),
        );
    }

    /**
     * Grants what a pending request asks for, until the chosen expiry (spec 112, FR-002, FR-003).
     *
     * Refused, and nothing granted, without a note, with an expiry it does not offer, with a
     * confirmation for anything else (or without the dangerous ability's name typed), or when the
     * access policy refuses what a role change would refuse. A request for an ability a request may
     * no longer ask for is cancelled instead (FR-008).
     */
    public function approveRequest(
        int $actorId,
        int $requestId,
        string $note,
        int $expiryDays,
        Confirmation $confirmation,
        DateTimeImmutable $now,
    ): OperationResult {
        $refusal = $this->notPending($requestId, $actorId, $now);
        $preview = $refusal === null ? $this->previewApproval($actorId, $requestId) : null;
        $refusal ??= $this->approvalRefusal($preview, $actorId, $note, $expiryDays, $confirmation, $now);
        if ($refusal !== null) {
            return $refusal;
        }

        if (! $this->requests->approve($requestId, $actorId, trim($note), $now, $now->modify(sprintf('+%d days', $expiryDays)))) {
            return $this->blocked('request_not_pending', __('The access request is no longer pending.', 'corex'), $now);
        }

        foreach ($preview->abilities as $ability) {
            $this->users->grantUserAbility($preview->requesterId, $ability->key);
        }

        return $this->decided($actorId, $requestId, 'approved', $note, $now, ['expiry_days' => $expiryDays]);
    }

    public function denyRequest(int $actorId, int $requestId, string $note, DateTimeImmutable $now): OperationResult
    {
        $refusal = $this->notPending($requestId, $actorId, $now);
        if ($refusal !== null) {
            return $refusal;
        }

        if (! $this->requests->transition($requestId, 'denied', $actorId, trim($note), $now)) {
            return $this->blocked('request_not_pending', __('The access request is no longer pending.', 'corex'), $now);
        }

        return $this->decided($actorId, $requestId, 'denied', $note, $now);
    }

    /** What a confirmation for approving this request is bound to: the request and what it grants. */
    public function approvalHash(int $requestId, array $abilityKeys): string
    {
        sort($abilityKeys);

        return hash('sha256', json_encode([
            'request'   => $requestId,
            'abilities' => $abilityKeys,
        ], JSON_THROW_ON_ERROR));
    }

    /** Why this pending request cannot be decided now, or null when it can. Expires it if its time ran out. */
    private function notPending(int $requestId, int $actorId, DateTimeImmutable $now): ?OperationResult
    {
        $request = $this->requests->find($requestId);

        if ($request === null || ($request['state'] ?? null) !== 'pending') {
            return $this->blocked('request_not_pending', __('The access request is no longer pending.', 'corex'), $now);
        }

        if (($request['expiresAt'] ?? null) instanceof DateTimeImmutable && $request['expiresAt'] <= $now) {
            $this->requests->transition($requestId, 'expired', $actorId, '', $now);

            return $this->blocked('request_expired', __('The access request has expired.', 'corex'), $now);
        }

        return null;
    }

    private function approvalRefusal(
        AccessApprovalPreview $preview,
        int $actorId,
        string $note,
        int $expiryDays,
        Confirmation $confirmation,
        DateTimeImmutable $now,
    ): ?OperationResult {
        if (! $this->stillRequestable($preview)) {
            $this->requests->cancel($preview->requestId, AccessRequestStore::CANCELLED_NOT_REQUESTABLE, $now);

            return $this->blocked('request_not_requestable', __('An administrator grants this through the role matrix, so the request was cancelled.', 'corex'), $now);
        }

        if (trim($note) === '') {
            return $this->blocked('note_required', __('Write a note saying why access is approved.', 'corex'), $now);
        }

        if (! in_array($expiryDays, self::GRANT_EXPIRY_DAYS, true)) {
            return $this->blocked('expiry_invalid', __('Choose an expiry of 7, 30 or 90 days.', 'corex'), $now);
        }

        if (! $confirmation->verify(self::APPROVAL_OPERATION, $preview->targetHash, $actorId, $preview->typedName, $now)) {
            return $this->blocked('confirmation_invalid', __('The approval confirmation is invalid or expired.', 'corex'), $now);
        }

        if ($preview->blockers !== []) {
            return $this->blocked('access_change_blocked', __('The approval was blocked by the access safety policy.', 'corex'), $now, $preview->blockers);
        }

        return null;
    }

    private function stillRequestable(AccessApprovalPreview $preview): bool
    {
        foreach ($preview->abilities as $ability) {
            if (! $this->requestable->isRequestable($ability->key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The name an approver types to grant a dangerous ability: its key, which is the same in every
     * language and is what the denied page names.
     *
     * @param list<CorexAbility> $abilities
     */
    private function typedName(array $abilities): ?string
    {
        foreach ($abilities as $ability) {
            if ($ability->risk === CorexAbility::RISK_DANGEROUS) {
                return $ability->key;
            }
        }

        return null;
    }

    /**
     * Records a decision and tells the requester.
     *
     * @param array<string,mixed> $context
     */
    private function decided(
        int $actorId,
        int $requestId,
        string $state,
        string $note,
        DateTimeImmutable $now,
        array $context = [],
    ): OperationResult {
        $request     = (array) $this->requests->find($requestId);
        $requesterId = (int) ($request['requesterId'] ?? 0);
        $kind        = 'access.request.' . $state;
        $approved    = $state === 'approved';
        $event       = $this->activity->record(
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
            context: ['requester_id' => $requesterId, 'decision' => $state, 'abilities' => $this->requestedAbilities($request)] + $context,
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: $now->modify('+180 days'),
            occurredAt: $now,
        );

        $this->notify($kind, [
            'request' => [
                'id'       => $requestId,
                'decision' => $state,
                'note'     => trim($note),
            ],
            'requester' => [
                'id'    => $requesterId,
                'name'  => $this->users->displayName($requesterId),
                'email' => $this->users->emailAddress($requesterId),
            ],
            'reviewer' => [
                'id'   => $actorId,
                'name' => $this->users->displayName($actorId),
            ],
        ]);

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

    /**
     * What a request grants: its ability, or every ability of its area.
     *
     * @param array<string,mixed> $request
     * @return list<string>
     */
    public function requestedAbilities(array $request): array
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
        // Who asks and how long the request stands are set by the code that calls this, never by
        // the person asking, so a wrong one is that code's mistake and not an `InvalidAccessInput`.
        if ($requesterId < 1 || $expiresAt <= $now) {
            throw new InvalidArgumentException('Access request details are invalid.');
        }

        if (trim($reason) === '') {
            throw new InvalidAccessInput('reason', InvalidAccessInput::REASON_REQUIRED, 'An access request needs a reason.');
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new InvalidAccessInput('reason', InvalidAccessInput::REASON_TOO_LONG, 'The access request reason is too long.');
        }

        $this->assertTarget($abilityKey, $areaKey);
    }

    /**
     * A request is for one ability or for one area, CoreX has to have it, and it must be one a
     * request may ask for (spec 112, FR-001).
     */
    private function assertTarget(?string $abilityKey, ?string $areaKey): void
    {
        if ($abilityKey === null && $areaKey === null) {
            throw new InvalidAccessInput('ability', InvalidAccessInput::TARGET_REQUIRED, 'An access request must name an ability or an area.');
        }

        if ($abilityKey !== null && $areaKey !== null) {
            throw new InvalidAccessInput('area', InvalidAccessInput::TARGET_AMBIGUOUS, 'An access request must name an ability or an area, not both.');
        }

        if ($abilityKey !== null && $this->catalog->find($abilityKey) === null) {
            throw new InvalidAccessInput('ability', InvalidAccessInput::ABILITY_UNKNOWN, 'The requested CoreX ability is unknown.');
        }

        if ($areaKey !== null && ! array_key_exists($areaKey, $this->catalog->grouped())) {
            throw new InvalidAccessInput('area', InvalidAccessInput::AREA_UNKNOWN, 'The requested CoreX area is unknown.');
        }

        if ($abilityKey !== null && ! $this->requestable->isRequestable($abilityKey)) {
            throw new InvalidAccessInput('ability', InvalidAccessInput::ABILITY_NOT_REQUESTABLE, 'The requested CoreX ability is granted only through the role matrix.');
        }

        if ($areaKey !== null && ! $this->requestable->isAreaRequestable($areaKey)) {
            throw new InvalidAccessInput('area', InvalidAccessInput::AREA_NOT_REQUESTABLE, 'The requested CoreX area holds an ability granted only through the role matrix.');
        }
    }

    private function assertRole(string $roleKey): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $roleKey) !== 1) {
            throw new InvalidAccessInput('role', InvalidAccessInput::ROLE_INVALID, 'Role key is invalid.');
        }
    }

    /** @param array<string,mixed> $context */
    private function notify(string $trigger, array $context): void
    {
        if ($this->notifications === null) {
            return;
        }

        try {
            $this->notifications->dispatch($trigger, $context);
        } catch (DomainException|InvalidArgumentException) {
            // Access state is authoritative; optional notification failure cannot roll back the decision.
        }
    }
}
