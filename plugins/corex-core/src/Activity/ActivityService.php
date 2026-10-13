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
            actorLabel: $this->fitted($actorLabel),
            area: $area,
            kind: $kind,
            targetType: $targetType,
            targetId: $this->fitted($targetId),
            targetLabel: $this->fitted($targetLabel),
            outcome: $outcome,
            summary: $summary,
            context: $context,
            sensitivity: $sensitivity,
            retentionUntil: $retentionUntil,
        );

        return $this->repository->append($event);
    }

    /**
     * A label, or the id of what was acted on, as the record can hold it.
     *
     * A caller hands over a person's display name, or the version a package says it is, and
     * neither has a length the caller chose. The table refused a longer one, and the request
     * that had already done its work ended in an error (DECISIONS #340). A text that was cut
     * ends with an ellipsis, so that it is not read as the whole of it.
     */
    private function fitted(string $text): string
    {
        if (mb_strlen($text, 'UTF-8') <= ActivityEvent::LONGEST_TEXT) {
            return $text;
        }

        return mb_substr($text, 0, ActivityEvent::LONGEST_TEXT - 1, 'UTF-8') . '…';
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
