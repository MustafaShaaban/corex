<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use DateTimeImmutable;

interface AccessRequestStore
{
    /** A grant an administrator took back (spec 112, FR-005). */
    public const ENDED_REVOKED = 'revoked';

    /** A grant whose expiry passed (spec 112, FR-006). */
    public const ENDED_EXPIRED = 'expired';

    /** A pending request for an ability a request may no longer ask for (spec 112, FR-008). */
    public const CANCELLED_NOT_REQUESTABLE = 'not_requestable';

    /**
     * Stores a request, unless this requester already has an open one for the same ability or area.
     *
     * The question and the storing are one step. A caller that asked {@see pendingFor()} and then
     * stored would let two asks that arrive together both find nothing open, and a second press of
     * a button is exactly two asks that arrive together. So there is no way to store a request
     * without the question being asked.
     *
     * @return int|null The new request's id, or null when one is already open and nothing was stored.
     */
    public function createUnlessPending(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): ?int;

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array;

    public function transition(
        int $id,
        string $state,
        int $reviewerId,
        string $note,
        DateTimeImmutable $reviewedAt,
    ): bool;

    /** @return list<array<string,mixed>> */
    public function pending(): array;

    /**
     * The one open request this requester already has for this ability or area, if any.
     *
     * Exactly one of $abilityKey / $areaKey is non-null, matching what a request is stored with — asking
     * for "any pending request by this user" would suppress a legitimate second request for a
     * different screen, which is a worse failure than showing the form twice.
     *
     * @return array<string,mixed>|null
     */
    public function pendingFor(int $requesterId, ?string $abilityKey, ?string $areaKey): ?array;

    /**
     * Approves a pending request: the request becomes a grant that ends at `$grantExpiresAt`.
     *
     * @return bool false when the request is no longer pending, and nothing changed.
     */
    public function approve(
        int $id,
        int $reviewerId,
        string $note,
        DateTimeImmutable $reviewedAt,
        DateTimeImmutable $grantExpiresAt,
    ): bool;

    /**
     * The grants that stand: approved and not ended, newest first.
     *
     * @return list<array<string,mixed>>
     */
    public function grants(): array;

    /**
     * Ends a standing grant, once.
     *
     * @param string   $reason  {@see ENDED_REVOKED} or {@see ENDED_EXPIRED}.
     * @param int|null $endedBy Who revoked it; null when its expiry ended it.
     *
     * @return bool false when the grant had already ended, or is not one.
     */
    public function endGrant(int $id, string $reason, ?int $endedBy, DateTimeImmutable $endedAt): bool;

    /**
     * The standing grants whose expiry is at or before `$now`.
     *
     * @return list<array<string,mixed>>
     */
    public function expiredGrants(DateTimeImmutable $now): array;

    /**
     * Cancels a pending request, once, keeping why.
     *
     * @return bool false when the request is no longer pending.
     */
    public function cancel(int $id, string $reason, DateTimeImmutable $cancelledAt): bool;
}
