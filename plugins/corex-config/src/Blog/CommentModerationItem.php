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
    /**
     * @param string $content     What the comment actually says. A moderation queue that shows only an
     *                            author and a state cannot be moderated from — you have to open each
     *                            comment somewhere else to decide (spec 075, FR-3).
     * @param string $submittedAt When it arrived, ATOM. Order and age are most of the judgement.
     */
    public function __construct(
        public int $commentId,
        public int $postId,
        public string $author,
        public string $state,
        public bool $firstComment,
        public bool $likelySpam,
        public bool $heldForReview,
        public string $content = '',
        public string $submittedAt = '',
    ) {
    }
}
