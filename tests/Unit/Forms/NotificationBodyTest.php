<?php

/**
 * The submission notification is HTML, and answerable — issue #138, items 4.
 *
 * `SendEmailListener` built its body with `NotificationDispatcher::plainTextBody()`, which separates
 * fields with newlines, and every CoreX transport sets `Content-Type: text/html`. HTML collapses
 * whitespace, so the notification arrived as one unbroken run. It also carried no `Reply-To`, so the
 * recipient of a contact form could not reply to the person who filled it in — the address was
 * readable only inside the body.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Submission\NotificationDispatcher;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->alias(static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES));
    Functions\when('esc_html__')->returnArg();
    Functions\when('wp_json_encode')->alias(static fn (mixed $d): string => (string) json_encode($d));
});

it('puts each field in its own row, so an HTML client does not collapse them into one line', function () {
    $html = NotificationDispatcher::htmlBody(['name' => 'Ada', 'message' => 'Hello']);

    // The property that matters is structural separation, not any particular tag: the old body's
    // fields were separated by "\n", which HTML treats as a space.
    expect(substr_count($html, '<tr>'))->toBe(2)
        ->and($html)->toContain('Ada')
        ->and($html)->toContain('Hello');
});

it('escapes a value that contains markup', function () {
    $html = NotificationDispatcher::htmlBody(['message' => '<script>alert(1)</script>']);

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('keeps the line breaks inside a multi-line answer', function () {
    // A textarea answer is the common case, and it is the one an HTML body destroys silently.
    $html = NotificationDispatcher::htmlBody(['message' => "first\nsecond"]);

    expect($html)->toContain('<br');
});

it('renders a field whose value is an array rather than printing "Array"', function () {
    $html = NotificationDispatcher::htmlBody(['topics' => ['one', 'two']]);

    expect($html)->toContain('one')
        ->and($html)->not->toContain('Array');
});

it('says so plainly when a submission carried no fields', function () {
    expect(NotificationDispatcher::htmlBody([]))->toContain('no fields');
});

it('still offers the plain-text form for a transport that wants it', function () {
    // Deliberately not replaced: returning HTML from a method named plainTextBody is how the
    // content-type mismatch happened in the first place.
    $text = NotificationDispatcher::plainTextBody(['name' => 'Ada', 'message' => 'Hello']);

    expect($text)->toBe("name: Ada\nmessage: Hello");
});
