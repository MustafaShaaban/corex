<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * What the operator asked for: the reset mode, dry-run and typed-safeguard state,
 * plus whether network scope was explicit. The command builds this pure value object
 * from CLI flags; the planner and gates read it.
 */
final class ResetRequest
{
    public const SOFT = 'soft';
    public const FULL = 'full';

    public function __construct(
        public readonly string $mode = self::SOFT,
        public readonly bool $dryRun = false,
        public readonly bool $confirmed = false,
        public readonly bool $network = false,
    ) {
    }

    public function isFull(): bool
    {
        return $this->mode === self::FULL;
    }
}
