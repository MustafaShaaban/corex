<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\DateTime;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Turns whatever a caller happens to be holding into a moment in time, or into nothing.
 *
 * CoreX stores timestamps in three shapes, all of them legitimate and all of them in the database
 * right now: `gmdate(DATE_ATOM)` strings from the newer repositories, `'Y-m-d H:i:s'` from the ones
 * that mirror WordPress's own column format, and bare Unix integers from a few option payloads.
 * A presenter that only understood one of them would silently mis-read the other two, so parsing
 * is a first-class step here rather than an assumption at each call site.
 *
 * The important behaviour is what happens to a value that is not a timestamp: this returns null.
 * It never throws, never substitutes "now", and never lands on the Unix epoch — the three ways a
 * malformed date normally reaches a screen looking like a real one.
 */
final class Instant
{
    /**
     * A naive `'Y-m-d H:i:s'` carries no zone. CoreX writes those with `gmdate()`, so they are UTC;
     * reading them as server-local time would shift every one of them by the server's offset.
     */
    private const NAIVE_PATTERN = '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/';

    /**
     * Timestamps outside this range are not dates a CoreX record can hold — they are a corrupt
     * value, a millisecond timestamp mistaken for seconds, or a sentinel. Treating them as absent
     * is more truthful than rendering "20 November 33658".
     */
    private const EARLIEST = -2208988800; // 1900-01-01
    private const LATEST   = 4102444800;  // 2100-01-01

    /**
     * The floor for a value written as a bare integer.
     *
     * Higher than `EARLIEST` on purpose. A four-digit string like `'2026'` is all digits, so it
     * parses as a Unix timestamp — 2026 seconds after the epoch, a date in January 1970. That is
     * the exact fabrication FR-018 forbids, arriving through the front door. A bare integer below
     * 2000 is not a timestamp anything in this product wrote; it is a year, an ID, or a count that
     * reached the wrong field. A *written-out* date stays governed by `EARLIEST`, because a spelled
     * date is a statement.
     */
    private const EARLIEST_UNIX = 946684800; // 2000-01-01

    /**
     * @param int|string|DateTimeImmutable|null $value A stored or transported timestamp.
     * @return DateTimeImmutable|null The instant it names, or null when it names none.
     */
    public static function parse(int|string|DateTimeImmutable|null $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return self::withinRange($value);
        }

        if (is_int($value)) {
            return self::fromUnix($value);
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        // A string of digits is a Unix timestamp that survived a round trip through JSON or a
        // meta column. `is_numeric` would also accept '1.5e3' and ' 12 ', which are not timestamps.
        if (preg_match('/^-?\d+$/', $text) === 1) {
            return self::fromUnix((int) $text);
        }

        if (preg_match(self::NAIVE_PATTERN, $text) === 1) {
            return self::fromNaiveUtc($text);
        }

        return self::fromOffsetBearing($text);
    }

    /**
     * A non-positive integer timestamp is a sentinel, not a date.
     *
     * Zero is how an unset integer column and a null-coerced-to-int both arrive, and rendering it
     * as `1 January 1970` is the classic tell that nothing was ever recorded. CoreX already treats
     * it that way where it guards by hand — `OperationsSecurityScreen` renders a date only when
     * `$entry['time'] > 0` — so the rule is being centralised here rather than invented.
     *
     * An ISO string that genuinely names a 1969 date still parses, because a written-out date is a
     * statement and an integer 0 is an absence.
     */
    private static function fromUnix(int $seconds): ?DateTimeImmutable
    {
        if ($seconds < self::EARLIEST_UNIX || $seconds > self::LATEST) {
            return null;
        }

        try {
            return new DateTimeImmutable('@' . $seconds);
        } catch (Throwable) {
            return null;
        }
    }

    private static function fromNaiveUtc(string $text): ?DateTimeImmutable
    {
        try {
            $parsed = new DateTimeImmutable($text, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }

        return self::withinRange($parsed);
    }

    /**
     * An ISO string carrying `Z` or an offset already knows its own zone, so the fallback zone
     * passed here is never consulted for a well-formed value. It matters only for the malformed
     * ones, which the range check then rejects.
     */
    private static function fromOffsetBearing(string $text): ?DateTimeImmutable
    {
        try {
            $parsed = new DateTimeImmutable($text, new DateTimeZone('UTC'));
        } catch (Throwable) {
            return null;
        }

        // `DateTimeImmutable` accepts a great deal that is not a timestamp — 'now', 'next tuesday',
        // '+1 day'. Those parse without error and would render as a real date, so anything that did
        // not carry its own date is rejected here.
        if (! self::looksLikeADate($text)) {
            return null;
        }

        return self::withinRange($parsed);
    }

    private static function looksLikeADate(string $text): bool
    {
        return preg_match('/\d{4}-\d{2}-\d{2}/', $text) === 1
            || preg_match('/^\d{8}T?\d{6}/', $text) === 1;
    }

    private static function withinRange(DateTimeImmutable $value): ?DateTimeImmutable
    {
        $seconds = $value->getTimestamp();

        return $seconds >= self::EARLIEST && $seconds <= self::LATEST ? $value : null;
    }
}
