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
    /**
     * @param bool $hasData Whether analytics saw this post at all in the window (spec 075, FR-4).
     *
     *                      A count of zero cannot distinguish "this post has never been opened" from
     *                      "analytics has never seen this post", and those call for opposite
     *                      reactions — the old panel showed four large zeros and left the reader to
     *                      guess. It is also true when every counted metric is zero but something
     *                      happened: a share click with no views is real activity.
     */
    public function __construct(
        public int $postId,
        public int $views,
        public int $reads,
        public int $shareClicks,
        public int $uniqueVisitors,
        public int $averageReadSeconds,
        public bool $hasData = false,
    ) {
    }
}
