<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

/**
 * The outcome of an application attempt: stored (with its id) or rejected (with a reason).
 */
final class ApplicationResult
{
    private function __construct(
        public readonly bool $stored,
        public readonly string $reason,
        public readonly ?int $id,
    ) {
    }

    public static function stored(int $id): self
    {
        return new self(true, '', $id);
    }

    public static function rejected(string $reason): self
    {
        return new self(false, $reason, null);
    }
}
