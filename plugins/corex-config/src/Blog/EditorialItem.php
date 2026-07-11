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
 * Native post editorial state plus CoreX review metadata.
 */
final readonly class EditorialItem
{
    public const STATE_DRAFT = 'draft';
    public const STATE_READY_FOR_REVIEW = 'ready_for_review';
    public const STATE_NEEDS_CHANGES = 'needs_changes';
    public const STATE_APPROVED = 'approved';
    public const STATE_SCHEDULED = 'scheduled';
    public const STATE_PUBLISHED = 'published';

    private const STATES = [
        self::STATE_DRAFT,
        self::STATE_READY_FOR_REVIEW,
        self::STATE_NEEDS_CHANGES,
        self::STATE_APPROVED,
        self::STATE_SCHEDULED,
        self::STATE_PUBLISHED,
    ];

    /**
     * @param list<EditorialNote> $notes
     */
    public function __construct(
        public int $postId,
        public string $editorialState,
        public string $nativeStatus,
        public ?int $assigneeId = null,
        public ?DateTimeImmutable $dueAt = null,
        public ?DateTimeImmutable $scheduledAt = null,
        public array $notes = [],
    ) {
        if ($this->postId < 1) {
            throw new InvalidArgumentException('Editorial item post ID is invalid.');
        }

        if (! in_array($this->editorialState, self::STATES, true)) {
            throw new InvalidArgumentException('Editorial state is invalid.');
        }

        foreach ($this->notes as $note) {
            if (! $note instanceof EditorialNote) {
                throw new InvalidArgumentException('Editorial notes must contain EditorialNote instances.');
            }
        }
    }
}
