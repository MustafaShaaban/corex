<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * The times visitors have asked for (spec 129, FR-021): one claim per time, which only one request can make.
 *
 * A claim is pending while its request is being taken, then names the submission it became, so it can be
 * released when that submission is binned. Times are UTC seconds.
 */
interface SlotClaims
{
    /** @return list<int> every claimed time, pending or not */
    public function claimed(): array;

    /** Claims a time for a request being taken; false when it is already claimed. */
    public function claim(int $slot): bool;

    /** Names the submission a pending claim became. */
    public function confirm(int $slot, int $submissionId): void;

    /** Releases a pending claim whose request was not stored. A confirmed claim is left alone. */
    public function release(int $slot): void;

    /** Releases the claim a submission holds, if it holds one. */
    public function releaseFor(int $submissionId): void;

    /** Removes the claims of times before a moment: nobody can be offered those again. */
    public function forgetBefore(int $moment): void;
}
