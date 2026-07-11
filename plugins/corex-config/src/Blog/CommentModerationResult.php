<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Result of a native WordPress comment moderation action.
 */
final readonly class CommentModerationResult
{
    public function __construct(
        public int $commentId,
        public string $action,
        public string $state,
        public ?int $createdCommentId = null,
    ) {
    }
}
