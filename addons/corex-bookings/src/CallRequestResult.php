<?php

/**
 * @package Corex\Bookings
 */

declare(strict_types=1);

namespace Corex\Bookings;

defined('ABSPATH') || exit;

/**
 * The outcome of a call request: stored (with its id) or rejected (with a reason).
 */
final class CallRequestResult
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
