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
 * Immutable reusable content-partial revision.
 */
final class EmailPartial
{
    public function __construct(
        public readonly int $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $htmlBody,
        public readonly string $plainText,
        public readonly string $status,
        public readonly int $version,
        public readonly int $createdBy,
        public readonly DateTimeImmutable $createdAt,
    ) {
        if ($this->id < 0 || $this->version < 1 || $this->createdBy < 0 || preg_match('/^[a-z][a-z0-9-]*$/', $this->slug) !== 1) {
            throw new InvalidArgumentException(__('Email partial metadata is invalid.', 'corex'));
        }

        if (! in_array($this->status, ['draft', 'active', 'inactive'], true)) {
            throw new InvalidArgumentException(__('Email partial status is invalid.', 'corex'));
        }

        if (! in_array($this->kind, ['header', 'footer', 'unsubscribe', 'preferences', 'privacy', 'custom'], true)) {
            throw new InvalidArgumentException(__('Email partial kind is invalid.', 'corex'));
        }
    }
}
