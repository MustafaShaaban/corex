<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Native comment projected for the Blog Pro moderation queue.
 */
final readonly class CommentModerationItem
{
    public function __construct(
        public int $commentId,
        public int $postId,
        public string $author,
        public string $state,
        public bool $firstComment,
        public bool $likelySpam,
        public bool $heldForReview,
    ) {
    }
}
