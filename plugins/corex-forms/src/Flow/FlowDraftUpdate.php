<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Immutable optimistic-write command for a new draft snapshot.
 */
final readonly class FlowDraftUpdate
{
    public function __construct(
        public int $flowId,
        public FlowConfiguration $configuration,
        public int $expectedVersion,
        public string $expectedChecksum,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
