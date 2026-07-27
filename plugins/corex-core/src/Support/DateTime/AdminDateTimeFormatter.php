<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\DateTime;

use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * The one place CoreX turns an instant into words.
 *
 * Two decisions are worth reading before the code.
 *
 * **The format is CoreX's, not the site's.** The three server-side surfaces this replaces each
 * built their format from `get_option('date_format') . ' ' . get_option('time_format')`, so a date
 * looked different on every install and could not be matched by a browser-side renderer. The
 * owner's requirement is a fixed presentation — `1 August 2026 at 10:20 PM` — so Settings → General
 * no longer governs CoreX admin dates. That is a deliberate departure, recorded in the spec's
 * Assumptions and in DECISIONS.
 *
 * **The connector is a `sprintf` pattern, not part of the date format.** Writing "at" inside a PHP
 * date format means escaping it as `\a\t`, which is a trap for any translator who does not know
 * that `a` means meridiem and `t` means days-in-month. Joining two formatted halves with
 * `%1$s at %2$s` lets a translator reorder both halves and replace the connector without touching
 * a format character.
 */
final class AdminDateTimeFormatter implements AdminDateTime
{
    /**
     * Thresholds for the relative phrasing, in seconds. Past a week, "51 weeks ago" stops helping
     * anyone place an event, so the absolute date is shown instead.
     */
    private const MINUTE = 60;
    private const HOUR   = 3600;
    private const DAY    = 86400;
    private const WEEK   = 604800;

    public function format(
        int|string|DateTimeImmutable|null $value,
        string $kind = self::FULL,
        string $absent = '',
    ): Formatted {
        $instant = Instant::parse($value);

        if ($instant === null) {
            return Formatted::absent(
                $absent !== '' ? $absent : __('Not recorded', 'corex')
            );
        }

        $timestamp = $instant->getTimestamp();
        $machine   = gmdate(DATE_ATOM, $timestamp);

        return Formatted::of($this->human($timestamp, $kind), $machine);
    }

    /**
     * @return array<string, mixed>
     */
    public function clientConfig(): array
    {
        $locale = $GLOBALS['wp_locale'] ?? null;

        return [
            'timezone' => $this->timezonePayload(),
            'locale'   => determine_locale(),
            'isRtl'    => is_rtl(),
            // Already-translated words. The browser composes with these and never asks its own
            // platform for a month name: `Intl` reads CLDR, WordPress reads the `corex` and core
            // translation files, and in Arabic those disagree (أغسطس against آب). One dictionary.
            'months'      => $this->monthNames($locale),
            'monthsShort' => $this->monthNamesShort($locale),
            'meridiem'    => $this->meridiem($locale),
            // The same translated pattern strings the server uses, so a translator's reordering
            // applies to both sides at once and cannot drift.
            'patterns' => [
                'date'      => $this->dateFormat(),
                'time'      => $this->timeFormat(),
                'exactTime' => $this->exactTimeFormat(),
                'connector' => $this->connectorPattern(),
            ],
            'relative' => $this->relativeStrings(),
            'absent'   => __('Not recorded', 'corex'),
        ];
    }

    private function human(int $timestamp, string $kind): string
    {
        $timezone = wp_timezone();

        return match ($kind) {
            self::DATE     => (string) wp_date($this->dateFormat(), $timestamp, $timezone),
            self::TIME     => (string) wp_date($this->timeFormat(), $timestamp, $timezone),
            self::RELATIVE => $this->relative($timestamp),
            self::EXACT    => $this->join(
                (string) wp_date($this->dateFormat(), $timestamp, $timezone),
                (string) wp_date($this->exactTimeFormat(), $timestamp, $timezone),
            ),
            default => $this->join(
                (string) wp_date($this->dateFormat(), $timestamp, $timezone),
                (string) wp_date($this->timeFormat(), $timestamp, $timezone),
            ),
        };
    }

    private function join(string $date, string $time): string
    {
        return sprintf($this->connectorPattern(), $date, $time);
    }

    /**
     * A phrase for how long ago something happened.
     *
     * Clock skew between the server and a stored value can put an instant a few seconds in the
     * future; that reads as "just now" rather than as a negative duration, because a negative
     * duration is always a bug being shown to an operator.
     */
    private function relative(int $timestamp): string
    {
        $elapsed = time() - $timestamp;

        if ($elapsed < self::MINUTE) {
            return __('Just now', 'corex');
        }

        if ($elapsed < self::HOUR) {
            $minutes = (int) round($elapsed / self::MINUTE);

            /* translators: %d: number of minutes. */
            return sprintf(_n('%d minute ago', '%d minutes ago', $minutes, 'corex'), $minutes);
        }

        if ($elapsed < self::DAY) {
            $hours = (int) round($elapsed / self::HOUR);

            /* translators: %d: number of hours. */
            return sprintf(_n('%d hour ago', '%d hours ago', $hours, 'corex'), $hours);
        }

        if ($elapsed < self::WEEK) {
            $days = (int) round($elapsed / self::DAY);

            /* translators: %d: number of days. */
            return sprintf(_n('%d day ago', '%d days ago', $days, 'corex'), $days);
        }

        // Beyond a week the absolute date is the more useful answer, and the only one that stays
        // precise as it ages.
        return $this->human($timestamp, self::FULL);
    }

    private function dateFormat(): string
    {
        /* translators: PHP date format for a CoreX admin date. Default renders "1 August 2026". */
        return _x('j F Y', 'CoreX admin date format', 'corex');
    }

    private function timeFormat(): string
    {
        /* translators: PHP date format for a CoreX admin time. Default renders "10:20 PM". */
        return _x('g:i A', 'CoreX admin time format', 'corex');
    }

    private function exactTimeFormat(): string
    {
        /* translators: PHP date format for a CoreX admin time with seconds, for diagnostics. */
        return _x('g:i:s A', 'CoreX admin exact time format', 'corex');
    }

    private function connectorPattern(): string
    {
        return _x(
            /* translators: 1: a date, 2: a time. Renders "1 August 2026 at 10:20 PM". */
            '%1$s at %2$s',
            'CoreX admin date and time',
            'corex'
        );
    }

    /**
     * Named zone when the site has one, otherwise the offset it was configured with.
     *
     * A site configured by `gmt_offset` has no daylight-saving rules to apply — that is a property
     * of how it was configured, not a simplification — so the browser can do plain epoch arithmetic
     * for it, and must not be handed a zone name that does not exist.
     *
     * @return array{name: string|null, offsetMinutes: int}
     */
    private function timezonePayload(): array
    {
        $name = (string) get_option('timezone_string', '');

        return [
            'name'          => $name !== '' ? $name : null,
            'offsetMinutes' => (int) round((float) get_option('gmt_offset', 0) * 60),
        ];
    }

    /**
     * @return list<string>
     */
    private function monthNames(?object $locale): array
    {
        if ($locale === null || ! method_exists($locale, 'get_month')) {
            return [];
        }

        return array_map(
            static fn (int $month): string => (string) $locale->get_month((string) $month),
            range(1, 12),
        );
    }

    /**
     * @return list<string>
     */
    private function monthNamesShort(?object $locale): array
    {
        if ($locale === null || ! method_exists($locale, 'get_month_abbrev')) {
            return [];
        }

        return array_map(
            static fn (int $month): string => (string) $locale->get_month_abbrev(
                (string) $locale->get_month((string) $month)
            ),
            range(1, 12),
        );
    }

    /**
     * @return array<string, string>
     */
    private function meridiem(?object $locale): array
    {
        if ($locale === null || ! method_exists($locale, 'get_meridiem')) {
            return ['am' => 'am', 'pm' => 'pm', 'AM' => 'AM', 'PM' => 'PM'];
        }

        $resolve = static function (string $key) use ($locale): string {
            $translated = (string) $locale->get_meridiem($key);

            // WP_Locale returns '' for a locale that never translated the marker; the untranslated
            // key is the correct fallback, and an empty AM/PM would silently drop half the time.
            return $translated !== '' ? $translated : $key;
        };

        return [
            'am' => $resolve('am'),
            'pm' => $resolve('pm'),
            'AM' => $resolve('AM'),
            'PM' => $resolve('PM'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function relativeStrings(): array
    {
        return [
            'justNow' => __('Just now', 'corex'),
            /* translators: %d: number of minutes. */
            'minutes' => [
                'one'   => __('%d minute ago', 'corex'),
                'other' => __('%d minutes ago', 'corex'),
            ],
            /* translators: %d: number of hours. */
            'hours' => [
                'one'   => __('%d hour ago', 'corex'),
                'other' => __('%d hours ago', 'corex'),
            ],
            /* translators: %d: number of days. */
            'days' => [
                'one'   => __('%d day ago', 'corex'),
                'other' => __('%d days ago', 'corex'),
            ],
        ];
    }
}
