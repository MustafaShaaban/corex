<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Immutable lifecycle transition command.
 */
final readonly class FlowTransition
{
    public function __construct(
        public int $flowId,
        public int $expectedDraftVersion,
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
