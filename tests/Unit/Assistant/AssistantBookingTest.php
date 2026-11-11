<?php

/**
 * The CoreX Assistant's booking hours (spec 129, "pick a time"): which moments a visitor may ask for,
 * and how a saved value is read back.
 *
 * @package Corex\Tests\Unit\Assistant
 */

declare(strict_types=1);

use Corex\Assistant\Booking\BookingHours;

/** A Monday, 2026-11-09 06:00 in Cairo (UTC+2): 04:00 UTC. */
const ASSISTANT_MONDAY_6AM_CAIRO = 1_794_196_800;

function assistantHours(array $saved): BookingHours
{
    return BookingHours::fromArray(['enabled' => true, 'timezone' => 'Africa/Cairo'] + $saved, 'UTC');
}

it('offers nothing until the owner turns the booking on', function () {
    expect(BookingHours::fromArray([], 'Africa/Cairo')->slots(ASSISTANT_MONDAY_6AM_CAIRO))->toBe([])
        ->and(BookingHours::fromArray([], 'Africa/Cairo')->timezone)->toBe('Africa/Cairo');
});

it('offers each working day’s slots in the business’s zone, after the notice, for the days shown', function () {
    $hours = assistantHours(['days' => [1, 2], 'start' => '09:00', 'end' => '11:00', 'slot' => 60, 'notice' => 240, 'horizon' => 3]);

    $slots = array_map(
        static fn (int $at): string => (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone('Africa/Cairo'))->format('D H:i'),
        $hours->slots(ASSISTANT_MONDAY_6AM_CAIRO),
    );

    // Monday 09:00 is three hours away, inside the four hours' notice; 10:00 is the first.
    expect($slots)->toBe(['Mon 10:00', 'Tue 09:00', 'Tue 10:00']);
});

it('closes the next day when closing time is at or before opening time', function () {
    $hours = assistantHours(['days' => [1], 'start' => '22:00', 'end' => '01:00', 'slot' => 60, 'notice' => 0, 'horizon' => 1]);

    $slots = array_map(
        static fn (int $at): string => (new DateTimeImmutable('@' . $at))->setTimezone(new DateTimeZone('Africa/Cairo'))->format('D H:i'),
        $hours->slots(ASSISTANT_MONDAY_6AM_CAIRO),
    );

    expect($slots)->toBe(['Mon 22:00', 'Mon 23:00', 'Tue 00:00'])
        ->and($hours->hoursIn('Asia/Riyadh', ASSISTANT_MONDAY_6AM_CAIRO))->toBe('23:00 – 02:00');
});

it('keeps only what it can use from a saved value, and reads its own output the same', function () {
    $hours = BookingHours::fromArray([
        'enabled' => 1,
        'timezone' => 'Mars/Olympus',
        'days' => ['3', 3, 9, 'x', 0],
        'start' => '25:00',
        'end' => '18:30',
        'slot' => 7,
        'notice' => 99999,
        'horizon' => 0,
    ], 'Asia/Dubai');

    expect($hours->toArray())->toBe([
        'enabled' => true,
        'timezone' => 'Asia/Dubai',
        'days' => [0, 3],
        'start' => '09:00',
        'end' => '18:30',
        'slot' => 30,
        'notice' => BookingHours::MAX_NOTICE,
        'horizon' => 1,
    ])
        ->and(BookingHours::fromArray($hours->toArray(), 'UTC')->toArray())->toBe($hours->toArray());
});
