<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Editable template identity and pointers to its immutable revisions.
 */
final class EmailTemplate
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $status,
        public readonly int $draftVersion,
        public readonly int $activeVersion,
        public readonly int $updatedBy,
        public readonly DateTimeImmutable $updatedAt,
    ) {
        if ($this->id < 1 || $this->updatedBy < 1 || $this->draftVersion < 0 || $this->activeVersion < 0) {
            throw new InvalidArgumentException(__('Email template identifiers and versions are invalid.', 'corex'));
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->uuid) !== 1) {
            throw new InvalidArgumentException(__('Email template UUID must be a valid version 4 UUID.', 'corex'));
        }

        if (preg_match('/^[a-z][a-z0-9-]*$/', $this->slug) !== 1 || trim($this->name) === '') {
            throw new InvalidArgumentException(__('Email template name and slug are invalid.', 'corex'));
        }

        if (! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_INACTIVE], true)) {
            throw new InvalidArgumentException(__('Email template status is invalid.', 'corex'));
        }
    }
}
