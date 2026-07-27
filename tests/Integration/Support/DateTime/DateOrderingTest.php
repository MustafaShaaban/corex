<?php

/**
 * Human dates must not cost chronological order (spec 076, T035 / FR-016).
 *
 * This is a **verification**, not a change. No CoreX table sorts dates in the browser — the
 * Submission Inbox and the Data explorer both order server-side through `WP_Query`'s
 * `orderby => 'date'`, on the stored column — so replacing the rendered text could not have
 * reordered anything.
 *
 * That is exactly why it is worth a test. "Sorting is unaffected because it happens somewhere else"
 * is an argument, and the failure mode it guards against is somebody later reaching for the
 * displayed string because it is the value nearest to hand. `1 August 2026` sorts before
 * `1 December 2025` alphabetically, and a month-name sort looks plausible until December.
 *
 * @package Corex\Tests\Integration\Support\DateTime
 */

declare(strict_types=1);

use Corex\Support\DateTime\AdminDateTime;
use Corex\Support\DateTime\AdminDateTimeFormatter;

/**
 * Dates chosen so that alphabetical and chronological order disagree, and so that the disagreement
 * spans a month, a year, and a leap day.
 *
 * Alphabetically by month name: April, August, December, February, January.
 * Chronologically: December 2023, January 2024, February 2024 (leap), April, August.
 *
 * All in the past deliberately. `wp_insert_post` reclassifies a future-dated post from `publish` to
 * `future`, so a fixture dated 2028 silently vanishes from a `post_status => 'publish'` query — the
 * first run of this test found three of five rows missing for exactly that reason. 2024 is a leap
 * year, so the boundary is kept without the interaction.
 */
const ORDERED_UTC = [
    '2023-12-31T22:30:00Z',
    '2024-01-15T09:00:00Z',
    '2024-02-29T12:00:00Z',
    '2024-04-01T08:00:00Z',
    '2024-08-20T18:45:00Z',
];

beforeEach(function () {
    $this->originalTimezone = get_option('timezone_string');
    update_option('timezone_string', 'Africa/Cairo');
    $this->formatter = new AdminDateTimeFormatter();
});

afterEach(function () {
    update_option('timezone_string', $this->originalTimezone);
});

it('keeps the machine value sortable when the human text is not', function () {
    $formatted = array_map(
        fn (string $utc) => $this->formatter->format($utc),
        ORDERED_UTC,
    );

    $machine = array_map(fn ($item) => $item->machine, $formatted);
    $human   = array_map(fn ($item) => $item->human, $formatted);

    $sortedMachine = $machine;
    sort($sortedMachine);

    $sortedHuman = $human;
    sort($sortedHuman);

    // The machine values sort chronologically as plain strings — that is what ISO 8601 is for, and
    // it is why FR-016 asks tables to sort on them.
    expect($sortedMachine)->toBe($machine);

    // The human text does NOT, and this asserts that rather than hoping nobody tries it. If this
    // ever passes, the format has changed into something month-name-sortable by accident, and the
    // reason the rule exists has quietly evaporated.
    expect($sortedHuman)->not->toBe($human);
});

it('orders submissions chronologically across month, year and leap-day boundaries', function () {
    $ids = [];

    // Inserted in a deliberately jumbled order, so a query that preserved insertion order would
    // fail rather than coincidentally pass.
    foreach ([2, 0, 4, 1, 3] as $index) {
        $ids[$index] = wp_insert_post([
            'post_type'     => 'corex_submission',
            'post_status'   => 'publish',
            'post_title'    => 'Ordering fixture ' . $index,
            'post_date_gmt' => gmdate('Y-m-d H:i:s', strtotime(ORDERED_UTC[$index])),
            'post_date'     => gmdate('Y-m-d H:i:s', strtotime(ORDERED_UTC[$index])),
        ]);
    }

    $query = new WP_Query([
        'post_type'      => 'corex_submission',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'post__in'       => array_values($ids),
        'fields'         => 'ids',
    ]);

    ksort($ids);

    expect($query->posts)->toBe(array_values($ids));

    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }
});

it('groups absent dates rather than scattering them', function () {
    // An absent value carries no machine string at all, so it cannot land in the middle of a
    // chronological run — it sorts to one end and stays there.
    $withAbsent = [
        $this->formatter->format('2024-01-15T09:00:00Z')->machine,
        $this->formatter->format(null, AdminDateTime::FULL, 'Never')->machine,
        $this->formatter->format('2023-12-31T22:30:00Z')->machine,
        $this->formatter->format(0, AdminDateTime::FULL, 'Not recorded')->machine,
    ];

    sort($withAbsent);

    expect(array_slice($withAbsent, 0, 2))->toBe(['', ''])
        ->and(array_slice($withAbsent, 2))->toBe([
            '2023-12-31T22:30:00+00:00',
            '2024-01-15T09:00:00+00:00',
        ]);
});
