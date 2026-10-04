<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * One request to change the operations mode (spec 101): which mode, who is asking, when, and what
 * they confirmed. It carries both kinds of confirmation because the caller does not know which
 * one the mode needs — {@see ModeChangeService} does, and ignores the other.
 */
final readonly class ModeChangeRequest
{
    public function __construct(
        public string $mode,
        public int $actorId,
        public DateTimeImmutable $now,
        public bool $acknowledged = false,
        public string $phrase = '',
    ) {
    }
}
