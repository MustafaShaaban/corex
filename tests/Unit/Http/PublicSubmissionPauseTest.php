<?php

/**
 * Who public submissions are closed to, by which mode, and what they are told (spec 124, FR-004;
 * spec 112, User Story 3). One answer for Maintenance and Forms paused, so the two modes cannot
 * disagree about who passes. The REST gate that acts on it is proven through the real REST server
 * in `tests/Integration/Operations/MaintenanceClosesSubmissionsTest.php`.
 *
 * @package Corex\Tests\Unit\Http
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Http\PublicSubmissionPause;
use Corex\Http\VisitorMessage;
use Corex\Http\VisitorMessages;

function submissionPause(string $mode, bool $loggedIn = false, bool $canManage = false): PublicSubmissionPause
{
    Functions\when('__')->returnArg();
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $key === 'corex_operations_mode' ? $mode : $default);
    Functions\when('is_user_logged_in')->justReturn($loggedIn);
    Functions\when('current_user_can')->justReturn($canManage);

    return new PublicSubmissionPause();
}

it('closes submissions in Maintenance and in Forms paused to everyone but a signed-in administrator (FR-004)', function (string $mode, bool $loggedIn) {
    expect(submissionPause($mode, $loggedIn)->closedBy())->toBe($mode);
})->with([
    'maintenance, not signed in'                    => ['maintenance', false],
    'maintenance, signed in, not an administrator'  => ['maintenance', true],
    'forms paused, not signed in'                   => ['forms-paused', false],
    'forms paused, signed in, not an administrator' => ['forms-paused', true],
]);

it('lets a signed-in administrator submit in either mode (FR-004)', function (string $mode) {
    expect(submissionPause($mode, true, true)->closedBy())->toBeNull();
})->with(['maintenance', 'forms-paused']);

it('keeps submissions open in every other mode (FR-004)', function (string $mode) {
    expect(submissionPause($mode)->closedBy())->toBeNull();
})->with(['development', 'staging', 'production', 'coming-soon', '', 'nonsense']);

it('keeps the Maintenance emergency bypass for Maintenance only (DECISIONS #123)', function () {
    Filters\expectApplied('corex_maintenance_bypass')->once()->andReturn(true);

    expect(submissionPause('maintenance')->closedBy())->toBeNull();
});

it('does not open Forms paused through the Maintenance bypass', function () {
    Filters\expectApplied('corex_maintenance_bypass')->never();

    expect(submissionPause('forms-paused')->closedBy())->toBe('forms-paused');
});

it('tells the visitor each mode\'s own sentence, Maintenance\'s exactly as before (FR-004)', function () {
    $pause = submissionPause('maintenance');

    expect($pause->message('maintenance'))
        ->toBe('The site is closed for maintenance and is not taking submissions. Please try again later.')
        ->and($pause->message('forms-paused'))
        ->toBe('Forms are paused for a short while. Please try again later.');
});

it('names each refusal by a code of its own, Maintenance\'s unchanged (FR-004)', function () {
    $pause = submissionPause('maintenance');

    expect($pause->code('maintenance'))->toBe('site_in_maintenance')
        ->and($pause->code('forms-paused'))->toBe('forms_paused');
});

it('asks a refused client to come back in an hour (FR-004; slice 9 adds the back-by time)', function () {
    expect(submissionPause('forms-paused')->retryAfter())->toBe(3600);
});

/*
 * Spec 124, FR-021: the paused panel's words, from what the owner tells visitors (FR-007, stored by
 * slice 9) or CoreX's own: the title, the message and the back-by line.
 */

/** A pause that says what the owner set for Forms paused. */
function pauseSaying(VisitorMessage $message): PublicSubmissionPause
{
    submissionPause('forms-paused');
    Functions\when('_x')->returnArg();
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => match ($key) {
        'corex_operations_mode' => 'forms-paused',
        'date_format' => 'j F Y',
        'time_format' => 'H:i',
        default => $default,
    });
    Functions\when('wp_date')->alias(static fn (string $format, int $timestamp): string => gmdate($format, $timestamp));

    return new PublicSubmissionPause(new VisitorMessages([PublicSubmissionPause::FORMS_PAUSED => $message]));
}

const PAUSE_NOW = '2026-11-20T10:00:00+00:00';

it('FR-021: says CoreX\'s own words, with no back-by line, while nothing is set', function () {
    $pause = submissionPause('forms-paused');
    Functions\when('_x')->returnArg();

    expect($pause->pausedNotice(new DateTimeImmutable(PAUSE_NOW)))->toBe([
        'title' => 'Paused',
        'message' => 'Forms are paused for a short while. Please try again later.',
        'when' => '',
    ]);
});

it('FR-021: says the owner\'s message whole, up to 300 characters, and refuses with it too', function () {
    $long = substr(str_repeat('Back after the yearly stocktake. ', 10), 0, 299) . '.';
    expect(mb_strlen($long))->toBe(300);

    $pause = pauseSaying(new VisitorMessage($long));

    expect($pause->pausedNotice(new DateTimeImmutable(PAUSE_NOW))['message'])->toBe($long)
        ->and($pause->message(PublicSubmissionPause::FORMS_PAUSED))->toBe($long);
});

it('FR-021: says when forms are back, in the site\'s formats, while that time is ahead', function () {
    $pause = pauseSaying(new VisitorMessage('', new DateTimeImmutable('2026-11-21T14:30:00+00:00')));

    expect($pause->pausedNotice(new DateTimeImmutable(PAUSE_NOW)))->toMatchArray([
        'message' => 'Forms are paused for a short while. Please try again later.',
        'when' => 'Back by 21 November 2026 at 14:30.',
    ]);
});

it('FR-021: never shows a back-by time that has passed, and says it is taking longer instead', function (string $backBy) {
    $pause = pauseSaying(new VisitorMessage('', new DateTimeImmutable($backBy)));

    expect($pause->pausedNotice(new DateTimeImmutable(PAUSE_NOW))['when'])->toBe('This is taking longer than planned.');
})->with(['an hour ago' => '2026-11-20T09:00:00+00:00', 'this very minute' => PAUSE_NOW]);
