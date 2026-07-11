<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Activity;

defined('ABSPATH') || exit;

use DateTimeImmutable;

interface ActivityRepository
{
    public function append(ActivityEvent $event): ActivityEvent;

    public function find(int $id): ?ActivityEvent;

    /**
     * @param array<string,mixed> $filters
     *
     * @return list<ActivityEvent>
     */
    public function query(array $filters = [], int $page = 1, int $perPage = 20): array;

    public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int;
}
