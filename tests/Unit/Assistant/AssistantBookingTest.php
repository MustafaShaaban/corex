<?php

/**
 * Booking hours (spec 129, User Story 9, FR-020 to FR-022): which times the hand-off offers, how the
 * route answers, and how a picked time reaches the inbox and the team, in both zones.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Corex\Assistant\Booking\BookingHours;
use Corex\Assistant\Booking\SlotsController;
use Corex\Assistant\Forms\AssistantCallbackForm;
use Corex\Tests\Unit\Assistant\AssistantFixtures;

/** A moment, written on a zone's clock. */
function at(string $wallClock, string $zone): int
{
    return (new DateTimeImmutable($wallClock, new DateTimeZone($zone)))->getTimestamp();
}

/** @param list<int> $slots @return list<string> */
function onClock(array $slots, string $zone): array
{
    return array_map(static fn (int $slot): string => (new DateTimeImmutable('@' . $slot))->setTimezone(new DateTimeZone($zone))->format('D H:i'), $slots);
}

/** @param array<string, mixed> $saved */
function hours(array $saved): BookingHours
{
    return BookingHours::fromSettings($saved + ['enabled' => true, 'timezone' => 'Africa/Cairo']);
}

it('offers the times inside the hours, from now plus the notice, a call ending by closing time', function () {
    // Monday 10 Nov 2025, 08:00 in Cairo; two hours' notice.
    $hours = hours(['days' => 'every', 'start' => '09:00', 'end' => '12:00', 'slot' => '60', 'notice' => '120', 'horizon' => '2']);

    expect(onClock($hours->slots(at('2025-11-10 08:00', 'Africa/Cairo')), 'Africa/Cairo'))
        ->toBe(['Mon 10:00', 'Mon 11:00', 'Tue 09:00', 'Tue 10:00', 'Tue 11:00']);
});

it('offers only the working days it is set to', function (string $days, array $offered) {
    $hours = hours(['days' => $days, 'start' => '09:00', 'end' => '10:00', 'slot' => '60', 'notice' => '0', 'horizon' => '7']);
    // Saturday 8 Nov 2025, before opening.
    $slots = onClock($hours->slots(at('2025-11-08 06:00', 'Africa/Cairo')), 'Africa/Cairo');

    expect(array_map(static fn (string $slot): string => substr($slot, 0, 3), $slots))->toBe($offered);
})->with([
    'Monday to Friday' => ['mon-fri', ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']],
    'Sunday to Thursday' => ['sun-thu', ['Sun', 'Mon', 'Tue', 'Wed', 'Thu']],
    'Saturday to Thursday' => ['sat-thu', ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu']],
    'every day' => ['every', ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri']],
]);

it('runs hours that end at or before they start past midnight', function () {
    $hours = hours(['days' => 'every', 'start' => '22:00', 'end' => '01:00', 'slot' => '60', 'notice' => '0', 'horizon' => '1']);

    expect(onClock($hours->slots(at('2025-11-10 12:00', 'Africa/Cairo')), 'Africa/Cairo'))->toBe(['Mon 22:00', 'Mon 23:00', 'Tue 00:00']);
});

it('keeps the hours on the business’s wall clock across a clock change', function () {
    // Europe/London leaves summer time on Sunday 26 Oct 2025.
    $hours = BookingHours::fromSettings(['enabled' => true, 'timezone' => 'Europe/London', 'days' => 'every', 'start' => '09:00', 'end' => '10:00', 'slot' => '60', 'notice' => '0', 'horizon' => '2']);

    expect(onClock($hours->slots(at('2025-10-25 06:00', 'Europe/London')), 'Europe/London'))->toBe(['Sat 09:00', 'Sun 09:00']);
});

it('offers nothing while booking is off', function () {
    expect(hours(['enabled' => false])->slots(at('2025-11-10 08:00', 'Africa/Cairo')))->toBe([]);
});

it('offers at most a screen of times', function () {
    $hours = hours(['days' => 'every', 'start' => '00:00', 'end' => '00:00', 'slot' => '15', 'notice' => '0', 'horizon' => '30']);

    expect($hours->slots(at('2025-11-10 00:00', 'Africa/Cairo')))->toHaveCount(BookingHours::MAX_SLOTS);
});

it('takes the values Settings show before the first save for anything missing or invalid', function () {
    $hours = BookingHours::fromSettings(['enabled' => true, 'timezone' => 'Not/AZone', 'days' => 'weekends', 'start' => '9am', 'slot' => '7', 'notice' => 'soon', 'horizon' => '99']);

    expect($hours->zone->getName())->toBe('UTC')
        ->and($hours->days)->toBe(BookingHours::WEEKS['mon-fri'])
        ->and([$hours->start, $hours->end, $hours->slot, $hours->notice, $hours->horizon])->toBe(['09:00', '17:00', 30, 120, 30]);
});

it('takes the site’s time zone written as an offset', function () {
    expect(BookingHours::fromSettings(['timezone' => '+02:00'])->zone->getName())->toBe('+02:00');
});

it('writes a picked time for the team in the visitor’s zone, then the business’s', function () {
    $line = hours([])->describe(at('2025-11-13 18:00', 'Africa/Cairo'), 'Asia/Riyadh', Closure::fromCallable([BookingHours::class, 'plainFormat']));

    expect($line)->toBe('Thu 13 Nov, 7:00 pm Asia/Riyadh = Thu 13 Nov, 6:00 pm Africa/Cairo');
});

it('reads a visitor zone it does not know as the business’s', function () {
    $line = hours([])->describe(at('2025-11-13 18:00', 'Africa/Cairo'), 'Mars/Olympus', Closure::fromCallable([BookingHours::class, 'plainFormat']));

    expect($line)->toBe('Thu 13 Nov, 6:00 pm Africa/Cairo = Thu 13 Nov, 6:00 pm Africa/Cairo');
});

it('stores a picked time as the team reads it, and drops a value that is not a moment', function (array $values, array $prepared) {
    $form = new AssistantCallbackForm(
        static fn (): BookingHours => hours([]),
        Closure::fromCallable([BookingHours::class, 'plainFormat']),
    );

    expect($form->prepare($values))->toBe($prepared);
})->with([
    'a time' => [
        ['name' => 'Sara', 'slot' => (string) at('2025-11-13 18:00', 'Africa/Cairo'), 'timezone' => 'Asia/Riyadh'],
        ['name' => 'Sara', 'slot' => 'Thu 13 Nov, 7:00 pm Asia/Riyadh = Thu 13 Nov, 6:00 pm Africa/Cairo', 'timezone' => 'Asia/Riyadh'],
    ],
    'no time' => [['name' => 'Sara', 'slot' => ''], ['name' => 'Sara', 'slot' => '']],
    'not a moment' => [['name' => 'Sara', 'slot' => 'tomorrow'], ['name' => 'Sara', 'slot' => '']],
]);

it('offers the times only while the assistant is live and booking is on', function (array $values, bool $offers) {
    $controller = new SlotsController(
        AssistantFixtures::settings($values + ['assistant.booking.days' => 'every', 'assistant.booking.notice' => '0']),
        static fn (): string => 'Africa/Cairo',
        static fn (): int => at('2025-11-10 08:00', 'Africa/Cairo'),
    );

    expect($controller->offered() !== [])->toBe($offers);
})->with([
    'live and on' => [['assistant.enabled' => '1', 'assistant.groq.key' => 'k', 'assistant.booking.enabled' => '1'], true],
    'booking off' => [['assistant.enabled' => '1', 'assistant.groq.key' => 'k'], false],
    'assistant off' => [['assistant.groq.key' => 'k', 'assistant.booking.enabled' => '1'], false],
]);
