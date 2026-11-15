<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Lockouts;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * The parts of a stored lockout notice the one-time rewrite reads (spec 124, FR-020).
 */
final readonly class StoredLockoutNotice
{
    /** @param array<string,mixed> $metadata */
    public function __construct(
        public int $id,
        public ?string $sourceType,
        public ?string $sourceId,
        public string $dedupKey,
        public array $metadata,
        public DateTimeImmutable $latestOccurredAt,
    ) {
    }
}
