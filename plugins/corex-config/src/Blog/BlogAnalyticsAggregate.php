<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * First-party aggregate for one post and window.
 */
final readonly class BlogAnalyticsAggregate
{
    public function __construct(
        public int $postId,
        public int $views,
        public int $reads,
        public int $shareClicks,
        public int $uniqueVisitors,
        public int $averageReadSeconds,
    ) {
    }
}
