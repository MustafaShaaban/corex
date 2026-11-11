<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One request to go live: the readiness evaluated when it was submitted, who asked, when, and the
 * phrase they typed (spec 124, US-OPS-05).
 */
final readonly class ProductionLaunchRequest
{
    public function __construct(
        public ReadinessSnapshot $snapshot,
        public int $actorId,
        public DateTimeImmutable $now,
        public string $phrase = '',
    ) {
        if ($this->actorId < 1) {
            throw new InvalidArgumentException('Production launch actor ID must be positive.');
        }
    }
}
