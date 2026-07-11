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
    public function create(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): int;

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
}
