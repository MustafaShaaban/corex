<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Immutable command for creating a flow and its first draft snapshot.
 */
final readonly class NewFlowDraft
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public int $ownerId,
        public string $placementType,
        public ?int $placementId,
        public bool $testMode,
        public FlowConfiguration $configuration,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
