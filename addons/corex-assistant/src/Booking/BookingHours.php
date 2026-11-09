<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * When the team takes calls, and the times a visitor may ask for (spec 129, User Story 9, FR-020).
 *
 * The hours are the business's, on its own wall clock in its own time zone. The slots go to the
 * browser as moments in time, so the visitor sees them in their own zone; the team reads a chosen one
 * in both. A slot is a requested time the team confirms, not a reservation: nothing here checks for a
 * time already asked for. Ported from Perego's `BookingHours` (spec 058 revision 2).
 */
final class BookingHours
{
    /** The working weeks Settings offer, as PHP numbers days (Sunday 0 to Saturday 6). */
    public const WEEKS = [
        'every' => [0, 1, 2, 3, 4, 5, 6],
        'mon-fri' => [1, 2, 3, 4, 5],
        'mon-sat' => [1, 2, 3, 4, 5, 6],
        'sun-thu' => [0, 1, 2, 3, 4],
        'sat-thu' => [0, 1, 2, 3, 4, 6],
    ];

    public const SLOT_LENGTHS = [15, 20, 30, 45, 60, 90, 120];

    /** A screen of slots at most: a month of quarter-hours would be too many to choose from. */
    public const MAX_SLOTS = 400;

    /** How a time reads for the team: "Thu 12 Nov, 7:00 pm". */
    public const TEAM_FORMAT = 'D j M, g:i a';

    /**
     * @param list<int> $days    the weekdays the team takes calls, Sunday 0 to Saturday 6
     * @param int       $slot    minutes a call lasts
     * @param int       $notice  minutes from now before the first time offered
     * @param int       $horizon days a visitor may choose from, today the first
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly \DateTimeZone $zone,
        public readonly array $days,
        public readonly string $start,
        public readonly string $end,
        public readonly int $slot,
        public readonly int $notice,
        public readonly int $horizon,
    ) {
    }

    /**
     * The hours as Settings hold them, each value checked; anything missing or invalid takes the
     * value Settings show before the first save. A zone PHP does not know is UTC.
     *
     * @param array<string, mixed> $saved enabled, timezone (a name or an offset such as +02:00), days
     *                                    (a key of {@see WEEKS}), start, end, slot, notice, horizon
     */
    public static function fromSettings(array $saved): self
    {
        $time = static fn (mixed $value, string $default): string => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $default;
        $number = static fn (mixed $value, int $default, int $min, int $max): int => is_numeric($value) ? min($max, max($min, (int) $value)) : $default;
        $slot = (int) ($saved['slot'] ?? 0);

        return new self(
            (bool) ($saved['enabled'] ?? false),
            self::zone((string) ($saved['timezone'] ?? '')),
            self::WEEKS[(string) ($saved['days'] ?? '')] ?? self::WEEKS['mon-fri'],
            $time($saved['start'] ?? null, '09:00'),
            $time($saved['end'] ?? null, '17:00'),
            in_array($slot, self::SLOT_LENGTHS, true) ? $slot : 30,
            $number($saved['notice'] ?? null, 120, 0, 2880),
            $number($saved['horizon'] ?? null, 7, 1, 30),
        );
    }

    /**
     * The moments a visitor may ask for, from `$now` plus the notice to the last day shown.
     *
     * Each working day opens at `start` and closes at `end` on the business's clock; an `end` at or
     * before `start` closes the next day (equal: round the clock). A call must end by closing time.
     *
     * @return list<int> UTC timestamps, in order, at most {@see MAX_SLOTS}
     */
    public function slots(int $now): array
    {
        if (! $this->enabled || $this->days === []) {
            return [];
        }

        $earliest = $now + $this->notice * 60;
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($this->zone)->setTime(0, 0);
        $slots = [];
        for ($offset = 0; $offset < $this->horizon; $offset++) {
            $day = $today->modify('+' . $offset . ' day');
            if (! in_array((int) $day->format('w'), $this->days, true)) {
                continue;
            }
            [$open, $close] = $this->openingOn($day);
            for ($at = $open; $at + $this->slot * 60 <= $close; $at += $this->slot * 60) {
                if ($at >= $earliest) {
                    $slots[] = $at;
                }
                if (count($slots) >= self::MAX_SLOTS) {
                    return $slots;
                }
            }
        }

        return $slots;
    }

    /**
     * A chosen time as the team reads it: in the visitor's zone, then the business's. A visitor zone
     * PHP does not know reads as the business's.
     *
     * @param \Closure(int, \DateTimeZone): string $format a moment on a zone's clock
     */
    public function describe(int $utc, string $visitorZone, \Closure $format): string
    {
        $visitor = in_array($visitorZone, \DateTimeZone::listIdentifiers(), true) ? new \DateTimeZone($visitorZone) : $this->zone;

        return $format($utc, $visitor) . ' ' . $visitor->getName() . ' = ' . $format($utc, $this->zone) . ' ' . $this->zone->getName();
    }

    /** A moment on a zone's clock in {@see TEAM_FORMAT}, without WordPress; `wp_date()` in production. */
    public static function plainFormat(int $utc, \DateTimeZone $zone): string
    {
        return (new \DateTimeImmutable('@' . $utc))->setTimezone($zone)->format(self::TEAM_FORMAT);
    }

    /** @return array{0: int, 1: int} opening and closing, UTC timestamps, for a day on the business's clock */
    private function openingOn(\DateTimeImmutable $day): array
    {
        [$openHour, $openMinute] = array_map('intval', explode(':', $this->start));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', $this->end));
        $open = $day->setTime($openHour, $openMinute);
        $close = $day->setTime($closeHour, $closeMinute);
        if ($close <= $open) {
            $close = $close->modify('+1 day');
        }

        return [$open->getTimestamp(), $close->getTimestamp()];
    }

    private static function zone(string $name): \DateTimeZone
    {
        try {
            return new \DateTimeZone($name !== '' ? $name : 'UTC');
        } catch (\Exception) {
            return new \DateTimeZone('UTC');
        }
    }
}
