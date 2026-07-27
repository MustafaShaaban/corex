<?php

/**
 * Integration tests for AdminDateTimeFormatter (spec 076, T009–T012).
 *
 * These run against real WordPress on purpose. The formatter is a presenter over `wp_date()` and
 * `wp_timezone()`, so stubbing those would test the stub: the question worth answering is whether
 * *WordPress* produces `1 August 2026 at 10:20 PM` for a site in Africa/Cairo, and whether it
 * follows the site through a daylight-saving change. Only a booted WordPress can answer that.
 *
 * Africa/Cairo is the zone under test throughout because it has real DST — +02:00 in winter,
 * +03:00 in summer, transitioning 2026-04-23 and 2026-10-29 — so the same formatter is exercised
 * on both sides of a transition without inventing a fixture.
 *
 * @package Corex\Tests\Integration\Support\DateTime
 */

declare(strict_types=1);

use Corex\Support\DateTime\AdminDateTime;
use Corex\Support\DateTime\AdminDateTimeFormatter;

/**
 * Point the site at a timezone for the duration of one test.
 *
 * WordPress caches nothing here — `wp_timezone()` reads the options each call — but the options are
 * global state, so every test restores them.
 */
function useSiteTimezone(?string $name, float $offsetHours = 0.0): void
{
    update_option('timezone_string', $name ?? '');
    update_option('gmt_offset', $name !== null ? 0 : $offsetHours);
}

beforeEach(function () {
    $this->originalTimezone = get_option('timezone_string');
    $this->originalOffset   = get_option('gmt_offset');
    $this->formatter        = new AdminDateTimeFormatter();
});

afterEach(function () {
    update_option('timezone_string', $this->originalTimezone);
    update_option('gmt_offset', $this->originalOffset);
});

// ---------------------------------------------------------------------------------------------
// T009 — the required presentation
// ---------------------------------------------------------------------------------------------

it('renders the format the owner specified', function () {
    useSiteTimezone('Africa/Cairo');

    // 19:20 UTC is 22:20 in Cairo on 1 August 2026 (DST, +03:00).
    $formatted = $this->formatter->format('2026-08-01T19:20:00Z');

    expect($formatted->human)->toBe('1 August 2026 at 10:20 PM');
});

it('renders each of the five presentations distinctly', function () {
    useSiteTimezone('Africa/Cairo');

    $value = '2026-08-01T19:20:24Z';

    expect($this->formatter->format($value, AdminDateTime::FULL)->human)
        ->toBe('1 August 2026 at 10:20 PM')
        ->and($this->formatter->format($value, AdminDateTime::DATE)->human)
        ->toBe('1 August 2026')
        ->and($this->formatter->format($value, AdminDateTime::TIME)->human)
        ->toBe('10:20 PM')
        ->and($this->formatter->format($value, AdminDateTime::EXACT)->human)
        ->toBe('1 August 2026 at 10:20:24 PM');
});

it('keeps seconds out of every presentation but EXACT', function () {
    useSiteTimezone('Africa/Cairo');

    // FR-004. The seconds here are :24, so a leaked second would be visible rather than
    // coincidentally zero — a test that passes for the wrong reason is the one worth avoiding.
    foreach ([AdminDateTime::FULL, AdminDateTime::DATE, AdminDateTime::TIME] as $kind) {
        expect($this->formatter->format('2026-08-01T19:20:24Z', $kind)->human)
            ->not->toContain(':24');
    }
});

it('says midnight and noon the way a person does', function (string $utc, string $expected) {
    useSiteTimezone('Africa/Cairo');

    expect($this->formatter->format($utc, AdminDateTime::TIME)->human)->toBe($expected);
})->with([
    // 21:00 UTC = 00:00 Cairo (+03:00). "12:00 AM", never "0:00 AM".
    'midnight' => ['2026-08-01T21:00:00Z', '12:00 AM'],
    // 09:00 UTC = 12:00 Cairo. "12:00 PM", never a bare 24-hour "12:00".
    'noon'     => ['2026-08-01T09:00:00Z', '12:00 PM'],
    'morning'  => ['2026-08-01T04:05:00Z', '7:05 AM'],
    'evening'  => ['2026-08-01T16:45:00Z', '7:45 PM'],
]);

it('drops the leading zero from the day and spells the month in full', function () {
    useSiteTimezone('Africa/Cairo');

    expect($this->formatter->format('2026-08-01T12:00:00Z', AdminDateTime::DATE)->human)
        ->toBe('1 August 2026')
        ->not->toContain('01 ')
        ->not->toContain('Aug ');
});

// ---------------------------------------------------------------------------------------------
// T010 — the timezone matrix
// ---------------------------------------------------------------------------------------------

it('shows the site timezone, not UTC and not the server', function () {
    useSiteTimezone('Africa/Cairo');
    $cairo = $this->formatter->format('2026-08-01T19:20:00Z')->human;

    useSiteTimezone('America/New_York');
    $newYork = $this->formatter->format('2026-08-01T19:20:00Z')->human;

    expect($cairo)->toBe('1 August 2026 at 10:20 PM')
        ->and($newYork)->toBe('1 August 2026 at 3:20 PM')
        ->and($cairo)->not->toBe($newYork);
});

it('follows the site across a daylight-saving transition', function () {
    useSiteTimezone('Africa/Cairo');

    // Cairo moves to +03:00 at 2026-04-23T22:00Z and back to +02:00 at 2026-10-29T21:00Z.
    // One hour either side of each transition, the same formatter must produce different offsets
    // without being told which applies.
    expect($this->formatter->format('2026-04-23T21:00:00Z', AdminDateTime::TIME)->human)
        ->toBe('11:00 PM') // still +02:00
        ->and($this->formatter->format('2026-04-23T22:30:00Z', AdminDateTime::TIME)->human)
        ->toBe('1:30 AM') // now +03:00
        ->and($this->formatter->format('2026-10-29T20:00:00Z', AdminDateTime::TIME)->human)
        ->toBe('11:00 PM') // still +03:00
        ->and($this->formatter->format('2026-10-29T22:00:00Z', AdminDateTime::TIME)->human)
        ->toBe('12:00 AM'); // back to +02:00
});

it('honours a site configured by raw offset instead of a named zone', function () {
    // WordPress permits this and it has no DST rules — a property of the configuration, not a
    // simplification. `wp_timezone()` synthesises the zone from gmt_offset.
    useSiteTimezone(null, 5.5); // UTC+5:30

    expect($this->formatter->format('2026-08-01T12:00:00Z', AdminDateTime::TIME)->human)
        ->toBe('5:30 PM');
});

it('honours a negative offset', function () {
    useSiteTimezone(null, -3.0);

    expect($this->formatter->format('2026-08-01T12:00:00Z', AdminDateTime::TIME)->human)
        ->toBe('9:00 AM');
});

it('displays UTC when the site really is UTC', function () {
    useSiteTimezone('UTC');

    expect($this->formatter->format('2026-08-01T19:20:00Z')->human)
        ->toBe('1 August 2026 at 7:20 PM');
});

it('reads every stored shape as the same displayed moment', function ($value) {
    useSiteTimezone('Africa/Cairo');

    expect($this->formatter->format($value)->human)->toBe('1 August 2026 at 10:20 PM');
})->with([
    'ISO with Z'      => '2026-08-01T19:20:00Z',
    'ISO with offset' => '2026-08-01T21:20:00+02:00',
    'naive UTC'       => '2026-08-01 19:20:00',
    'unix integer'    => 1785612000,
]);

// ---------------------------------------------------------------------------------------------
// T011 — boundaries
// ---------------------------------------------------------------------------------------------

it('crosses month, year and leap-day boundaries correctly', function (string $utc, string $expected) {
    useSiteTimezone('Africa/Cairo');

    expect($this->formatter->format($utc, AdminDateTime::DATE)->human)->toBe($expected);
})->with([
    // 22:30 UTC on 31 December is already 00:30 on 1 January in Cairo — the boundary case that
    // catches a formatter reading UTC parts and labelling them local.
    'year rollover'  => ['2026-12-31T22:30:00Z', '1 January 2027'],
    'month rollover' => ['2026-07-31T22:30:00Z', '1 August 2026'],
    'leap day'       => ['2028-02-29T12:00:00Z', '29 February 2028'],
    'day before leap' => ['2028-02-28T12:00:00Z', '28 February 2028'],
]);

// ---------------------------------------------------------------------------------------------
// T012 — absent and invalid
// ---------------------------------------------------------------------------------------------

it('renders the caller phrase for an absent value, never a fabricated date', function ($value) {
    useSiteTimezone('Africa/Cairo');

    $formatted = $this->formatter->format($value, AdminDateTime::FULL, 'No expiry');

    expect($formatted->human)->toBe('No expiry')
        ->and($formatted->isPresent)->toBeFalse()
        ->and($formatted->machine)->toBe('')
        // The four ways a bad date normally reaches a screen looking real.
        ->and($formatted->human)->not->toContain('1970')
        ->and($formatted->human)->not->toContain('Invalid')
        ->and($formatted->human)->not->toContain('NaN')
        ->and($formatted->human)->not->toContain('2026');
})->with([
    'null'      => null,
    'empty'     => '',
    'zero'      => 0,
    'malformed' => 'not-a-date',
    'relative'  => 'now',
]);

it('falls back to its own phrase when the caller supplies none', function () {
    expect($this->formatter->format(null)->human)->toBe('Not recorded');
});

it('emits no time element when there is nothing to point at', function () {
    $absent = $this->formatter->format(null, AdminDateTime::FULL, 'Never');

    // A <time> with an empty datetime claims a machine-readable value that does not exist.
    expect($absent->toHtml())->toBe('<span>Never</span>')
        ->and($absent->toHtml())->not->toContain('<time');
});

it('carries a valid machine value beside the human one', function () {
    useSiteTimezone('Africa/Cairo');

    $formatted = $this->formatter->format('2026-08-01T19:20:00Z');

    expect($formatted->machine)->toBe('2026-08-01T19:20:00+00:00')
        ->and($formatted->toHtml('corex-date'))
        ->toBe('<time datetime="2026-08-01T19:20:00+00:00" class="corex-date">1 August 2026 at 10:20 PM</time>');
});

// ---------------------------------------------------------------------------------------------
// Relative
// ---------------------------------------------------------------------------------------------

it('phrases recent moments relatively and old ones absolutely', function () {
    useSiteTimezone('Africa/Cairo');

    expect($this->formatter->format(time() - 10, AdminDateTime::RELATIVE)->human)
        ->toBe('Just now')
        ->and($this->formatter->format(time() - 120, AdminDateTime::RELATIVE)->human)
        ->toBe('2 minutes ago')
        ->and($this->formatter->format(time() - 7200, AdminDateTime::RELATIVE)->human)
        ->toBe('2 hours ago')
        ->and($this->formatter->format(time() - 172800, AdminDateTime::RELATIVE)->human)
        ->toBe('2 days ago');
});

it('does not describe a future instant as negative time ago', function () {
    useSiteTimezone('Africa/Cairo');

    // Clock skew between a stored value and the server puts instants slightly ahead. A negative
    // duration on a screen is always a bug being shown to an operator.
    expect($this->formatter->format(time() + 30, AdminDateTime::RELATIVE)->human)
        ->toBe('Just now');
});

it('stops using relative phrasing once it stops helping', function () {
    useSiteTimezone('Africa/Cairo');

    // Past a week, "51 weeks ago" places nothing. The absolute date is the more useful answer and
    // the only one that stays precise as it ages.
    $threeWeeksAgo = time() - (21 * 86400);

    expect($this->formatter->format($threeWeeksAgo, AdminDateTime::RELATIVE)->human)
        ->toBe($this->formatter->format($threeWeeksAgo, AdminDateTime::FULL)->human)
        ->and($this->formatter->format($threeWeeksAgo, AdminDateTime::RELATIVE)->human)
        ->not->toContain('ago');
});
