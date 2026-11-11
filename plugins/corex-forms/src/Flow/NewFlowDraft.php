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
        /**
         * Whether the flow is made from nothing, on the New flow form, and so starts with the
         * emails CoreX ships (spec 120, FR-020). A flow created from a configuration of its own
         * (a copy, an import) keeps the emails that configuration has.
         */
        public bool $defaultEmails = false,
    ) {
    }
}
