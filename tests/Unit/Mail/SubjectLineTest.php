<?php

/**
 * Unit tests for a subject as an email carries it: plain text, on one line.
 *
 * What was typed is kept as it was typed, because a subject is not markup; what cannot be in a
 * header (a line break, a control character) becomes a space, so that nothing after it is read
 * as a header of its own.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Security\HeaderGuard;
use Corex\Mail\SubjectLine;

it('keeps what was typed, as it was typed', function (string $typed) {
    expect(SubjectLine::of($typed))->toBe($typed);
})->with([
    'a less-than sign' => ['Price <500 EGP'],
    'an address in angle brackets' => ['Sam <sam@example.com> asked'],
    'a percent sign before two hex digits' => ['Save 50%de today'],
    'an ampersand and both quotes' => ['Tom & Jerry\'s "offer"'],
    'an entity somebody meant to type' => ['Use &amp; not &'],
    'a tag somebody meant to type' => ['About the <script> tag'],
    'a path' => ['C:\\offers\\new'],
    'two spaces' => ['Re:  two  spaces'],
    'Arabic' => ['بخصوص طلبك: السعر أقل من <500> جنيه'],
    'Arabic with the marks that order it' => ["\u{200F}رد: طلب #12\u{200E}"],
    'an emoji made of several' => ['Thank you 👨‍👩‍👧'],
    'a no-break space inside' => ["10\u{00A0}000 EGP"],
]);

it('makes one line of it, with one space where a break or a control character was', function (string $typed, string $line) {
    expect(SubjectLine::of($typed))->toBe($line);
})->with([
    'a line break' => ["One\nTwo", 'One Two'],
    'a Windows line break' => ["One\r\nTwo", 'One Two'],
    'a break with spaces round it' => ["One  \r\n\t  Two", 'One Two'],
    'several breaks' => ["One\n\n\nTwo", 'One Two'],
    'a tab' => ["One\tTwo", 'One Two'],
    'a null byte' => ["One\0Two", 'One Two'],
    'an escape and a delete' => ["One\x1BTwo\x7FThree", 'One Two Three'],
    'a next-line control' => ["One\u{0085}Two", 'One Two'],
    'the line and paragraph separators' => ["One\u{2028}Two\u{2029}Three", 'One Two Three'],
    'a break at each end' => ["\r\nOne\r\n", 'One'],
    'spaces at each end' => ['   One  ', 'One'],
]);

it('leaves nothing a header could be made of', function (string $typed) {
    $line = SubjectLine::of($typed);

    expect((new HeaderGuard())->inspect(['subject' => $line]))->toBeNull()
        ->and($line)->toContain('Bcc: victim@example.com');
})->with([
    ["Hello\r\nBcc: victim@example.com"],
    ["Hello\nBcc: victim@example.com"],
    ["Hello\rBcc: victim@example.com"],
    ["Hello\r\n\r\nBcc: victim@example.com"],
]);

it('cuts a subject at the longest one there is, by character and not by byte', function () {
    expect(SubjectLine::of(str_repeat('س', SubjectLine::LONGEST + 50)))->toBe(str_repeat('س', SubjectLine::LONGEST))
        ->and(SubjectLine::of(str_repeat('a', SubjectLine::LONGEST)))->toBe(str_repeat('a', SubjectLine::LONGEST))
        // Cut in a space, it does not end in one.
        ->and(SubjectLine::of(str_repeat('a', SubjectLine::LONGEST - 1) . ' b'))->toBe(str_repeat('a', SubjectLine::LONGEST - 1));
});

it('answers nothing for what is not text', function (mixed $typed) {
    expect(SubjectLine::of($typed))->toBe('');
})->with([
    'nothing' => [null],
    'a list' => [['Price', '500']],
    'a number' => [500],
    'bytes that are not UTF-8' => ["Price \xC3\x28 500"],
    'half a character' => ["Price \xD8"],
    'only spaces and breaks' => [" \r\n\t "],
    'an empty text' => [''],
]);
