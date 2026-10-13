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
}
