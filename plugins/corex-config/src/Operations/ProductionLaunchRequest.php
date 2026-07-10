<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProductionLaunchRequest
{
    public function __construct(
        public ReadinessSnapshot $snapshot,
        public int $actorId,
        public DateTimeImmutable $now,
        public ?ProductionLaunchOverride $override = null,
    ) {
        if ($this->actorId < 1) {
            throw new InvalidArgumentException('Production launch actor ID must be positive.');
        }
    }
}
