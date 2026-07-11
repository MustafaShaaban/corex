<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Requested transition for a native Blog post's CoreX editorial state.
 */
final readonly class EditorialTransitionRequest
{
    public function __construct(
        public int $postId,
        public string $state,
        public int $actorId,
        public string $note = '',
        public ?int $assigneeId = null,
        public ?DateTimeImmutable $dueAt = null,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?DateTimeImmutable $occurredAt = null,
    ) {
        if ($this->postId < 1) {
            throw new InvalidArgumentException('Editorial transition post ID is invalid.');
        }

        if ($this->actorId < 1) {
            throw new InvalidArgumentException('Editorial transition actor is invalid.');
        }
    }
}
