<?php

/**
 * The phone rule (#148 item 4). No WordPress.
 *
 * Contract: accept the numbers people actually type, reject what cannot be dialled, and defer
 * emptiness to `required` so an optional phone field stays possible.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Validation\Rules\Phone;

beforeEach(function () {
    $this->rule = new Phone();
});

/**
 * The formatting cases are the point of the rule. Before it there was no phone validation at all,
 * and the tempting replacement — a bare E.164 pattern — rejects `+20 101 699 9700` for its spaces,
 * which teaches a visitor the form is broken rather than that their number is.
 */
it('accepts a number however somebody chose to space it', function () {
    foreach ([
        '+201016999700',
        '+20 101 699 9700',
        '+1 (555) 010-0199',
        '+1.555.0100199',
        '442079460958',
        '+44 20 7946 0958',
    ] as $number) {
        expect($this->rule->validate($number, [], []))
            ->toBeNull("«{$number}» is a number somebody would type");
    }
});

it('rejects what cannot be dialled', function () {
    foreach ([
        'call me',
        '+20-abc-1234',
        '0123456789',        // E.164 forbids a leading zero after the country code
        '+0123456789',
        '1',                 // one digit is not a number anybody can ring
        '+123456789012345678',
    ] as $number) {
        expect($this->rule->validate($number, [], []))
            ->toBe('phone', "«{$number}» is not dialable");
    }
});

/**
 * Every rule in this directory defers emptiness to `required`. A rule that also enforced presence
 * would make an optional phone field impossible to express.
 */
it('leaves emptiness to the required rule', function () {
    expect($this->rule->validate('', [], []))->toBeNull()
        ->and($this->rule->validate('   ', [], []))->toBeNull()
        ->and($this->rule->validate(null, [], []))->toBeNull();
});

it('ignores a value that is not a scalar rather than stringifying it', function () {
    expect($this->rule->validate(['+201016999700'], [], []))->toBeNull();
});

/**
 * A number written the way its own country writes it starts with the trunk `0`, which E.164
 * forbids: `010 1699 9700` was refused by a contact form asking a local audience for "Phone"
 * (#249, reported 2026-10-06). `phone:national` accepts that form as well.
 */
it('accepts a number written with its trunk zero when the field allows national numbers', function (string $number) {
    expect($this->rule->validate($number, ['national'], []))->toBeNull();
})->with([
    'an Egyptian mobile' => ['010 1699 9700'],
    'a London landline' => ['(020) 7946-0958'],
    'the shortest it takes: a zero and six digits' => ['0123456'],
    'the longest: a zero and fourteen digits' => ['012345678901234'],
    'an international number, as before' => ['+20 101 699 9700'],
    'a national number with no trunk zero, as before' => ['555 010 0199'],
]);

it('still refuses what is not a number when the field allows national numbers', function (string $number) {
    expect($this->rule->validate($number, ['national'], []))->toBe('phone');
})->with([
    'words' => ['call me'],
    'a zero and five digits' => ['012345'],
    'a zero and fifteen digits' => ['0123456789012345'],
    'a plus before the trunk zero' => ['+0123456789'],
    'a single zero' => ['0'],
]);

it('goes on refusing a trunk zero on a field that did not ask for national numbers', function () {
    expect($this->rule->validate('010 1699 9700', [], []))->toBe('phone');
});
