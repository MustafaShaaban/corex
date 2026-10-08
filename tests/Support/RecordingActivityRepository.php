<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use DateTimeImmutable;

/**
 * An activity stream that keeps what was recorded, so a test can read it.
 */
final class RecordingActivityRepository implements ActivityRepository
{
    /** @var list<ActivityEvent> */
    public array $events = [];

    public function append(ActivityEvent $event): ActivityEvent
    {
        return $this->events[] = $event;
    }

    public function find(int $id): ?ActivityEvent
    {
        return null;
    }

    public function query(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->events;
    }

    public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
    {
        return 0;
    }
}
