<?php

/**
 * The claims of requested times over an array the test reads back (spec 129, FR-021). The database's
 * version, and its race, are tested on the real options table in `tests/Integration/Assistant`.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

namespace Corex\Tests\Unit\Assistant;

use Corex\Assistant\Booking\SlotClaims;

final class MemorySlotClaims implements SlotClaims
{
    /** @param array<int, int> $rows time => submission id, 0 while pending */
    public function __construct(public array $rows = [])
    {
    }

    public function claimed(): array
    {
        $slots = array_keys($this->rows);
        sort($slots);

        return $slots;
    }

    public function claim(int $slot): bool
    {
        if (isset($this->rows[$slot])) {
            return false;
        }
        $this->rows[$slot] = 0;

        return true;
    }

    public function confirm(int $slot, int $submissionId): void
    {
        if (($this->rows[$slot] ?? null) === 0) {
            $this->rows[$slot] = $submissionId;
        }
    }

    public function release(int $slot): void
    {
        if (($this->rows[$slot] ?? null) === 0) {
            unset($this->rows[$slot]);
        }
    }

    public function releaseFor(int $submissionId): void
    {
        $this->rows = array_filter($this->rows, static fn (int $held): bool => $held !== $submissionId || $submissionId <= 0);
    }

    public function forgetBefore(int $moment): void
    {
        $this->rows = array_filter($this->rows, static fn (int $slot): bool => $slot >= $moment, ARRAY_FILTER_USE_KEY);
    }
}
