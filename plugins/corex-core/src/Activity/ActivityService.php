<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Activity;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use Corex\Support\Uuid;

/**
 * Application boundary for recording and reading authoritative product activity.
 */
final class ActivityService
{
    public function __construct(private readonly ActivityRepository $repository)
    {
    }

    /**
     * @param array{key:string,args:array<string,mixed>} $summary
     * @param array<string,mixed>                        $context
     */
    public function record(
        int $actorId,
        string $actorKind,
        string $actorLabel,
        string $area,
        string $kind,
        string $targetType,
        string $targetId,
        string $targetLabel,
        string $outcome,
        array $summary,
        array $context,
        string $sensitivity,
        DateTimeImmutable $retentionUntil,
        ?DateTimeImmutable $occurredAt = null,
        ?string $eventUuid = null,
    ): ActivityEvent {
        $event = new ActivityEvent(
            id: 0,
            eventUuid: $eventUuid ?? Uuid::v4(),
            occurredAt: $occurredAt ?? new DateTimeImmutable('now'),
            actorId: $actorId,
            actorKind: $actorKind,
            actorLabel: $actorLabel,
            area: $area,
            kind: $kind,
            targetType: $targetType,
            targetId: $targetId,
            targetLabel: $targetLabel,
            outcome: $outcome,
            summary: $summary,
            context: $context,
            sensitivity: $sensitivity,
            retentionUntil: $retentionUntil,
        );

        return $this->repository->append($event);
    }

    public function find(int $id): ?ActivityEvent
    {
        return $this->repository->find($id);
    }

    /**
     * @param array<string,mixed> $filters
     *
     * @return list<ActivityEvent>
     */
    public function query(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->repository->query($filters, $page, $perPage);
    }

    public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
    {
        return $this->repository->pruneExpired($now, $limit);
    }

}
