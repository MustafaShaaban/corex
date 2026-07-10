<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;

interface ReadingEventStore
{
    public function append(ReadingEvent $event): void;

    /**
     * @return list<ReadingEvent>
     */
    public function between(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): array;
}
