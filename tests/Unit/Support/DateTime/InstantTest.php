<?php

/**
 * Unit tests for Instant (spec 076, T004).
 *
 * The contract under test is narrow and worth stating: every timestamp shape CoreX actually stores
 * resolves to the same moment, and everything else resolves to null. Null is the interesting half —
 * it is what keeps a corrupt value from reaching a screen dressed as a real date, which is how
 * `Invalid Date`, `NaN` and `01 January 1970` normally get in front of an operator.
 *
 * @package Corex\Tests\Unit\Support\DateTime
 */

declare(strict_types=1);

use Corex\Support\DateTime\Instant;

/** The same moment, written the five ways the codebase writes it. */
const MOMENT_UNIX = 1785610800; // 2026-08-01T19:00:00Z

it('reads the five stored shapes as the same moment', function (int|string $value) {
    expect(Instant::parse($value)?->getTimestamp())->toBe(MOMENT_UNIX);
})->with([
    'unix integer'      => MOMENT_UNIX,
    'unix as string'    => '1785610800',
    'ISO with Z'        => '2026-08-01T19:00:00Z',
    'ISO with offset'   => '2026-08-01T22:00:00+03:00',
    'naive, read as UTC' => '2026-08-01 19:00:00',
]);

it('reads a naive datetime as UTC, because that is what gmdate wrote', function () {
    // The whole point: `WpSubmissionsReader` and friends write with gmdate(), so a column with no
    // zone is UTC. Reading it as server-local would shift every stored value by the server offset —
    // silently, and differently on every host.
    $parsed = Instant::parse('2026-08-01 19:00:00');

    expect($parsed?->format('c'))->toBe('2026-08-01T19:00:00+00:00');
});

it('accepts a DateTimeImmutable unchanged', function () {
    $given = new DateTimeImmutable('2026-08-01T19:00:00+00:00');

    expect(Instant::parse($given)?->getTimestamp())->toBe(MOMENT_UNIX);
});

it('returns null rather than a date for anything that is not one', function ($value) {
    expect(Instant::parse($value))->toBeNull();
})->with([
    'null'            => null,
    'empty string'    => '',
    'whitespace'      => '   ',
    'not a date'      => 'nonsense',
    'partial'         => '2026-08',
    'malformed ISO'   => '2026-13-45T99:99:99Z',
    'a JSON null'     => 'null',
    'an em dash'      => '—',
]);

it('refuses relative expressions that PHP would happily parse', function ($value) {
    // `new DateTimeImmutable('now')` succeeds and returns a real date. A stored value of 'now' or
    // '+1 day' is corrupt data, and rendering it as today's date would be the most convincing
    // possible lie — it looks exactly like a working feature.
    expect(Instant::parse($value))->toBeNull();
})->with([
    'now'        => 'now',
    'today'      => 'today',
    'relative'   => '+1 day',
    'weekday'    => 'next tuesday',
    'epoch word' => '@0',
]);

it('rejects integer timestamps outside the range a record can hold', function ($value) {
    expect(Instant::parse($value))->toBeNull();
})->with([
    'after 2100'                  => 4102444801,
    'milliseconds mistaken for s' => 1785610800000,
]);

it('accepts the upper range boundary itself', function () {
    expect(Instant::parse(4102444800))->not->toBeNull();
});

it('treats a non-positive integer as absent, not as 1970', function ($value) {
    // Zero is how an unset integer column and a null-coerced-to-int both arrive, and
    // `1 January 1970` on a screen is the classic tell that one of them slipped through.
    // `OperationsSecurityScreen` already guards this by hand (`$entry['time'] > 0`); the rule
    // is centralised in Instant rather than repeated at each call site.
    expect(Instant::parse($value))->toBeNull();
})->with([
    'zero'            => 0,
    'zero as string'  => '0',
    'negative one'    => -1,
    'far negative'    => -2208988801,
]);

it('still reads a written-out pre-epoch date, because a stated date is not an absence', function () {
    // The asymmetry is deliberate: integer 0 is a sentinel, but '1969-07-20T20:17:00Z' is
    // somebody saying a date out loud.
    expect(Instant::parse('1969-07-20T20:17:00Z')?->format('Y'))->toBe('1969');
});
