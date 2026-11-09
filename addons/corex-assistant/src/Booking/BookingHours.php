<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * When the team takes calls, and the times a visitor may ask for (spec 129, "pick a time").
 *
 * The hours are the business's, in its own time zone (Perego: every day 9:00 to 23:00, Cairo time). The
 * slots go to the browser as moments in time, so a visitor sees them in their own time zone, and the
 * team reads a chosen slot in both. A slot is a requested time the team confirms, not a reservation:
 * nothing here checks for a slot already asked for.
 */
final class BookingHours
{
    public const SLOT_LENGTHS = [15, 20, 30, 45, 60, 90, 120];

    /** Off until the owner turns it on; every day, office hours, half-hour slots, two hours' notice, a week ahead. */
    public const DEFAULTS = [
        'enabled' => false,
        'days' => [0, 1, 2, 3, 4, 5, 6],
        'start' => '09:00',
        'end' => '17:00',
        'slot' => 30,
        'notice' => 120,
        'horizon' => 7,
    ];

    /** Minutes of notice and days ahead, at most. */
    public const MAX_NOTICE = 2880;
    public const MAX_HORIZON = 30;

    /** A screen of slots, at most: a month of quarter-hours would be too many to choose from. */
    private const MAX_SLOTS = 400;

    /**
     * @param list<int> $days    the weekdays the team takes calls, Sunday 0 to Saturday 6
     * @param int       $slot    minutes a slot lasts
     * @param int       $notice  minutes from now before the first slot
     * @param int       $horizon days ahead a visitor may choose from
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly string $timezone,
        public readonly array $days,
        public readonly string $start,
        public readonly string $end,
        public readonly int $slot,
        public readonly int $notice,
        public readonly int $horizon,
    ) {
    }

    /**
     * The saved hours, each value checked; anything missing or invalid takes its default. Reading its own
     * output gives the same hours back, so a value saved twice is saved the same.
     *
     * @param array<string, mixed> $saved
     * @param string               $siteZone the zone used while none is saved (the site's own)
     */
    public static function fromArray(array $saved, string $siteZone = 'UTC'): self
    {
        $d = self::DEFAULTS;
        $time = static fn (mixed $value, string $default): string => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : $default;
        $days = array_key_exists('days', $saved) && is_array($saved['days'])
            ? array_values(array_unique(array_filter(
                array_map(static fn (mixed $day): int => is_numeric($day) ? (int) $day : -1, $saved['days']),
                static fn (int $day): bool => $day >= 0 && $day <= 6,
            )))
            : $d['days'];
        sort($days);
        $zone = is_string($saved['timezone'] ?? null) ? $saved['timezone'] : '';
        $fallback = self::knownZone($siteZone) ? $siteZone : 'UTC';

        return new self(
            (bool) ($saved['enabled'] ?? $d['enabled']),
            self::knownZone($zone) ? $zone : $fallback,
            $days,
            $time($saved['start'] ?? null, $d['start']),
            $time($saved['end'] ?? null, $d['end']),
            in_array((int) ($saved['slot'] ?? 0), self::SLOT_LENGTHS, true) ? (int) $saved['slot'] : $d['slot'],
            isset($saved['notice']) && is_numeric($saved['notice']) ? min(self::MAX_NOTICE, max(0, (int) $saved['notice'])) : $d['notice'],
            isset($saved['horizon']) && is_numeric($saved['horizon']) ? min(self::MAX_HORIZON, max(1, (int) $saved['horizon'])) : $d['horizon'],
        );
    }

    public static function knownZone(string $zone): bool
    {
        return $zone !== '' && in_array($zone, \DateTimeZone::listIdentifiers(), true);
    }

    /** @return array{enabled: bool, timezone: string, days: list<int>, start: string, end: string, slot: int, notice: int, horizon: int} */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'timezone' => $this->timezone,
            'days' => $this->days,
            'start' => $this->start,
            'end' => $this->end,
            'slot' => $this->slot,
            'notice' => $this->notice,
            'horizon' => $this->horizon,
        ];
    }

    /**
     * The moments a visitor may ask for, from `$now` plus the notice to the horizon.
     *
     * Each working day opens at `start` and closes at `end` in the business's zone; an `end` at or before
     * `start` closes the next day (equal: round the clock). A slot must end by closing time.
     *
     * @return list<int> UTC timestamps, in order
     */
    public function slots(int $now): array
    {
        if (! $this->enabled || $this->days === []) {
            return [];
        }

        $zone = new \DateTimeZone($this->timezone);
        $earliest = $now + $this->notice * 60;
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
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

    /** The opening hours as another zone sees them on the day of `$now`, for the owner's preview. */
    public function hoursIn(string $zone, int $now): string
    {
        $there = new \DateTimeZone(self::knownZone($zone) ? $zone : $this->timezone);
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone($this->timezone))->setTime(0, 0);
        [$open, $close] = $this->openingOn($today);

        return (new \DateTimeImmutable('@' . $open))->setTimezone($there)->format('H:i')
            . ' – ' . (new \DateTimeImmutable('@' . $close))->setTimezone($there)->format('H:i');
    }

    /** @return array{0: int, 1: int} opening and closing, UTC timestamps, for a day in the business zone */
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
}
