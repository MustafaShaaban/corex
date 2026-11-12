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
