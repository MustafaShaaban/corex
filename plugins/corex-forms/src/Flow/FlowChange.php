<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Actor and timestamp attached to one immutable flow projection change.
 */
final readonly class FlowChange
{
    public function __construct(
        public int $actorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
