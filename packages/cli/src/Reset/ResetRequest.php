<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

/**
 * What the operator asked for: the reset mode, whether it is a dry run, and whether the
 * typed safeguard was supplied. A pure value object — the command builds it from the CLI
 * flags, the planner and gate read it.
 */
final class ResetRequest
{
    public const SOFT = 'soft';
    public const FULL = 'full';

    public function __construct(
        public readonly string $mode = self::SOFT,
        public readonly bool $dryRun = false,
        public readonly bool $confirmed = false,
    ) {
    }

    public function isFull(): bool
    {
        return $this->mode === self::FULL;
    }
}
