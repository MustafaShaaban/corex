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
 * Privacy-preserving first-party reading event.
 */
final readonly class ReadingEvent
{
    public const VIEW = 'view';
    public const READ = 'read';
    public const SHARE_CLICK = 'share_click';

    private const TYPES = [self::VIEW, self::READ, self::SHARE_CLICK];

    public function __construct(
        public int $postId,
        public string $eventType,
        public string $visitorHash,
        public DateTimeImmutable $occurredAt,
        public ?int $readingSeconds = null,
        public ?string $shareTarget = null,
    ) {
        if ($this->postId < 1) {
            throw new InvalidArgumentException('Reading event post ID is invalid.');
        }

        if (! in_array($this->eventType, self::TYPES, true)) {
            throw new InvalidArgumentException('Reading event type is invalid.');
        }

        if (preg_match('/^[0-9a-f]{64}$/', $this->visitorHash) !== 1) {
            throw new InvalidArgumentException('Reading event visitor hash must be SHA-256.');
        }

        if ($this->readingSeconds !== null && $this->readingSeconds < 0) {
            throw new InvalidArgumentException('Reading seconds cannot be negative.');
        }
    }
}
