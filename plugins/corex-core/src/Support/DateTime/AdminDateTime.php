<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\DateTime;

use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * How every CoreX admin surface turns a stored instant into something an operator can read.
 *
 * Five presentations and no sixth (spec 076, FR-002). The closed set is the point: before this
 * contract existed, each screen composed its own, and the product ended up showing raw ISO strings
 * on twelve surfaces, the reader's own browser timezone on six, and three mutually inconsistent
 * server-side formats built from the site's date/time options.
 *
 * The interface lives beside its callers rather than beside the implementation, per the
 * constitution's dependency rule — a screen depends on "a way to present a date", not on
 * `wp_date()`.
 */
interface AdminDateTime
{
    /** `1 August 2026 at 10:20 PM` — the ordinary case, and the only one most surfaces need. */
    public const FULL = 'full';

    /** `1 August 2026` — where a time would be noise, such as a due date. */
    public const DATE = 'date';

    /** `10:20 PM` — where the date is already established by its surroundings. */
    public const TIME = 'time';

    /** `2 hours ago` — recency, where the exact moment is secondary but must stay reachable. */
    public const RELATIVE = 'relative';

    /** `1 August 2026 at 10:20:24 PM` — seconds included. Diagnostics only (FR-004). */
    public const EXACT = 'exact';

    /**
     * Present an instant, in the site's timezone and the active locale.
     *
     * @param int|string|DateTimeImmutable|null $value  Any stored or transported timestamp.
     * @param string                            $kind   One of the five constants above.
     * @param string                            $absent What to say when there is no instant. The
     *                                                  caller chooses, because "Never", "No expiry"
     *                                                  and "Not recorded" are different statements
     *                                                  and only the field knows which it means.
     */
    public function format(
        int|string|DateTimeImmutable|null $value,
        string $kind = self::FULL,
        string $absent = '',
    ): Formatted;

    /**
     * The configuration a browser needs to produce identical output.
     *
     * Handed across the one admin config boundary (FR-008). It carries already-translated words —
     * month names, period markers, the connector pattern — because the browser must not translate
     * dates itself: `Intl` reads CLDR and WordPress reads the `corex` translation files, and in
     * Arabic those disagree. One dictionary, exported.
     *
     * @return array<string, mixed>
     */
    public function clientConfig(): array;
}
