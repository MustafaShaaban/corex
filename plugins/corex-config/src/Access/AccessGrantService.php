<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessGrantEndedEvent;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\RequestableAbilities;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Events\EventDispatcher;
use Corex\Operations\OperationResult;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * The grants made by approving access requests, once they stand (spec 112, User Story 1): listed,
 * revoked, ended by their expiry, and the pending requests a request may no longer make cancelled.
 *
 * An ability is taken back only when no other standing grant gives it to the same person: an
 * ability request and an area request can give the same one.
 */
final class AccessGrantService
{
    /** A grant whose ability was taken away outside CoreX, by a role editor or the command line. */
    public const NOT_HELD = 'removed_outside_corex';

    public function __construct(
        private readonly AccessRequestStore $requests,
        private readonly AccessUserDirectory $users,
        private readonly AccessService $access,
        private readonly RequestableAbilities $requestable,
        private readonly ActivityService $activity,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    /**
     * The standing grants, each with what it gives and whether the person still holds it. A grant
     * to an account that no longer exists gives nobody anything, and is not listed.
     *
     * @return list<array{grant:array<string,mixed>,abilities:list<string>,inEffect:bool}>
     */
    public function grants(): array
    {
        $standing = array_filter(
            $this->requests->grants(),
            fn (array $grant): bool => $this->users->exists((int) $grant['requesterId']),
        );

        return array_values(array_map(function (array $grant): array {
            $abilities = $this->access->requestedAbilities($grant);
            $held      = array_filter(
                $abilities,
                fn (string $ability): bool => $this->users->holdsOwnAbility((int) $grant['requesterId'], $ability),
            );

            return ['grant' => $grant, 'abilities' => $abilities, 'inEffect' => count($held) === count($abilities)];
        }, $standing));
    }

    public function revoke(int $actorId, int $grantId, DateTimeImmutable $now): OperationResult
    {
        $grant = $this->requests->find($grantId);

        if ($grant === null || ! $this->requests->endGrant($grantId, AccessRequestStore::ENDED_REVOKED, $actorId, $now)) {
            return $this->result(OperationResult::STATE_BLOCKED, __('That grant has already ended.', 'corex'), $now, [], 'grant_not_standing');
        }

        $removed = $this->takeBack($grant);
        $this->record($actorId, ActivityEvent::ACTOR_USER, 'access.grant.revoked', $grant, $now, ['removed' => $removed]);
        $this->events?->dispatch($this->ended($grant, AccessRequestStore::ENDED_REVOKED));

        return $this->result(OperationResult::STATE_COMPLETED, __('The grant was revoked.', 'corex'), $now, [$grantId]);
    }

    /** Ends every standing grant whose expiry has passed, and says how many. */
    public function endExpired(DateTimeImmutable $now): int
    {
        $ended = 0;

        foreach ($this->requests->expiredGrants($now) as $grant) {
            if (! $this->requests->endGrant((int) $grant['id'], AccessRequestStore::ENDED_EXPIRED, null, $now)) {
                continue;
            }

            $removed = $this->takeBack($grant);
            $this->record(0, ActivityEvent::ACTOR_CRON, 'access.grant.expired', $grant, $now, ['removed' => $removed]);
            $this->events?->dispatch($this->ended($grant, AccessRequestStore::ENDED_EXPIRED));
            $ended++;
        }

        return $ended;
    }

    /**
     * Cancels the pending requests for what a request may no longer ask for (FR-008), and says how
     * many. The person's denied page then says how access is given instead.
     */
    public function cancelNotRequestable(DateTimeImmutable $now): int
    {
        $cancelled = 0;

        foreach ($this->requests->pending() as $request) {
            if ($this->isRequestable($request)
                || ! $this->requests->cancel((int) $request['id'], AccessRequestStore::CANCELLED_NOT_REQUESTABLE, $now)) {
                continue;
            }

            $this->record(0, ActivityEvent::ACTOR_CRON, 'access.request.cancelled', $request, $now, ['reason' => AccessRequestStore::CANCELLED_NOT_REQUESTABLE]);
            $cancelled++;
        }

        return $cancelled;
    }

    /** @param array<string,mixed> $request */
    private function isRequestable(array $request): bool
    {
        return is_string($request['abilityKey'] ?? null)
            ? $this->requestable->isRequestable($request['abilityKey'])
            : $this->requestable->isAreaRequestable((string) ($request['areaKey'] ?? ''));
    }

    /**
     * Takes back what this grant gave, except what another standing grant still gives the person.
     *
     * @param array<string,mixed> $grant
     * @return list<string> What was taken back.
     */
    private function takeBack(array $grant): array
    {
        $person = (int) $grant['requesterId'];
        $kept   = [];
        foreach ($this->requests->grants() as $other) {
            // The ended grant is no longer among the standing ones.
            if ((int) $other['requesterId'] === $person) {
                array_push($kept, ...$this->access->requestedAbilities($other));
            }
        }

        $removed = array_values(array_diff($this->access->requestedAbilities($grant), $kept));
        foreach ($removed as $ability) {
            $this->users->revokeUserAbility($person, $ability);
        }

        return $removed;
    }

    /** @param array<string,mixed> $grant */
    private function ended(array $grant, string $reason): AccessGrantEndedEvent
    {
        return new AccessGrantEndedEvent(
            requestId: (int) $grant['id'],
            personId: (int) $grant['requesterId'],
            approverId: isset($grant['reviewerId']) ? (int) $grant['reviewerId'] : null,
            abilityKeys: $this->access->requestedAbilities($grant),
            reason: $reason,
        );
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,mixed> $context
     */
    private function record(int $actorId, string $actorKind, string $kind, array $request, DateTimeImmutable $now, array $context): void
    {
        $this->activity->record(
            actorId: $actorId,
            actorKind: $actorKind,
            actorLabel: $actorKind === ActivityEvent::ACTOR_USER ? $this->users->displayName($actorId) : 'CoreX',
            area: ActivityEvent::AREA_ACCESS,
            kind: $kind,
            targetType: 'access_request',
            targetId: (string) $request['id'],
            targetLabel: sprintf(
                /* translators: %d: access request ID. */
                __('Access request %d', 'corex'),
                (int) $request['id'],
            ),
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => $kind, 'args' => ['request' => (int) $request['id']]],
            context: ['person_id' => (int) $request['requesterId'], 'abilities' => $this->access->requestedAbilities($request)] + $context,
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: $now->modify('+180 days'),
            occurredAt: $now,
        );
    }

    /** @param list<int> $affected */
    private function result(string $state, string $message, DateTimeImmutable $now, array $affected, ?string $code = null): OperationResult
    {
        return new OperationResult(
            operationId: Uuid::v4(),
            state: $state,
            message: $message,
            errors: $code === null ? [] : [['code' => $code, 'message' => $message]],
            affectedIds: $affected,
            startedAt: $now,
            finishedAt: $now,
        );
    }
}
