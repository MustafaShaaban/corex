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
