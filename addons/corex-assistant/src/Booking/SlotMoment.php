<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * A requested time as the hand-off sends it and the inbox keeps it (spec 129, FR-021, FR-022).
 *
 * The chat sends the slot as the slots route gave it, UTC seconds. A page cached before slice 3 still
 * runs the script that sent it as an ISO 8601 moment, and the inbox stores it that way, so both are read.
 */
final class SlotMoment
{
    /** An ISO 8601 moment with its offset: what `Date.prototype.toISOString()` writes, and {@see write()}. */
    private const ISO = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d{1,6})?)?(?:Z|[+-]\d{2}:\d{2})$/';

    /** The moment in UTC seconds, or null for an empty answer or anything that is not a moment. */
    public static function read(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        $text = is_string($value) ? trim($value) : '';
        if ($text !== '' && ctype_digit($text) && strlen($text) <= 12) {
            return (int) $text > 0 ? (int) $text : null;
        }
        if (preg_match(self::ISO, $text) !== 1) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($text))->getTimestamp();
        } catch (\Exception) {
            return null;
        }
    }

    /** The moment as the inbox shows it: `2026-11-12T16:00:00Z`. */
    public static function write(int $at): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $at);
    }
}
