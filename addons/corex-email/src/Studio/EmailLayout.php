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
 * Immutable layout revision used by editable templates.
 */
final class EmailLayout
{
    /** @param array<string,string> $regions */
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly int $version,
        public readonly array $regions,
        public readonly ?string $dependency,
        public readonly int $createdBy,
        public readonly DateTimeImmutable $createdAt,
        public readonly string $status = 'active',
    ) {
        if ($this->id < 0 || $this->version < 1 || $this->createdBy < 0 || preg_match('/^[a-z][a-z0-9-]*$/', $this->slug) !== 1) {
            throw new InvalidArgumentException(__('Email layout metadata is invalid.', 'corex'));
        }

        if (! in_array($this->status, ['draft', 'active', 'inactive'], true)) {
            throw new InvalidArgumentException(__('Email layout status is invalid.', 'corex'));
        }
    }
}
