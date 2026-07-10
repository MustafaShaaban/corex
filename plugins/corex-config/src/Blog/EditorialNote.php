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
 * Actor-authored review note attached to a native Blog post.
 */
final readonly class EditorialNote
{
    public function __construct(
        public int $actorId,
        public string $body,
        public DateTimeImmutable $createdAt,
    ) {
        if ($this->actorId < 1) {
            throw new InvalidArgumentException('Editorial note actor is invalid.');
        }

        if (trim($this->body) === '') {
            throw new InvalidArgumentException('Editorial note body is required.');
        }
    }
}
