<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Requested moderation action for one native WordPress comment.
 */
final readonly class CommentModerationRequest
{
    public function __construct(
        public int $commentId,
        public string $action,
        public int $actorId,
        public string $body = '',
    ) {
        if ($this->commentId < 1) {
            throw new InvalidArgumentException('Comment moderation target is invalid.');
        }

        if ($this->actorId < 1) {
            throw new InvalidArgumentException('Comment moderation actor is invalid.');
        }
    }
}
