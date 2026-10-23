<?php

/**
 * Which test email counts for the launch checklist (spec 118, US-OVR-03).
 *
 * Contract: a test from Email Studio that the transport accepted (sent or opened) in the window;
 * a captured or failed test, a test older than the window, or an email that was not a test does
 * not count.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Email\Delivery\EmailAttempt;
use Corex\Email\Delivery\EmailAttemptRepository;
use Corex\Tests\Support\InMemoryEmailStudioStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_salt')->justReturn('unit-test-salt');
});

function recordedAttempt(EmailAttemptRepository $attempts, string $state, string $source, string $when): void
{
    $attempts->record(new EmailAttempt(
        id: 0,
        attemptId: sprintf('%08x-0000-4000-8000-%012x', random_int(0, 0xffffffff), random_int(0, 0xffffffffffff)),
        requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        parentAttemptId: null,
        recipient: 'owner@example.com',
        subject: 'A test',
        templateSlug: null,
        state: $state,
        provider: 'wp-mail',
        providerEvent: null,
        retryable: false,
        occurredAt: new DateTimeImmutable($when),
        source: $source,
    ));
}

it('counts only a test the transport accepted inside the window', function (string $state, string $source, string $when, bool $counts) {
    $attempts = new EmailAttemptRepository(new InMemoryEmailStudioStore());
    recordedAttempt($attempts, $state, $source, $when);

    $found = $attempts->latestAcceptedTest(new DateTimeImmutable('2026-07-25T00:00:00+00:00'));

    expect($found !== null)->toBe($counts);
})->with([
    'a test, sent' => [EmailAttempt::STATE_SENT, 'test', '2026-10-01T00:00:00+00:00', true],
    'a test, opened' => [EmailAttempt::STATE_OPENED, 'test', '2026-10-01T00:00:00+00:00', true],
    'a test, captured' => [EmailAttempt::STATE_CAPTURED, 'test', '2026-10-01T00:00:00+00:00', false],
    'a test, failed' => [EmailAttempt::STATE_FAILED, 'test', '2026-10-01T00:00:00+00:00', false],
    'a test before the window' => [EmailAttempt::STATE_SENT, 'test', '2026-07-01T00:00:00+00:00', false],
    'not a test' => [EmailAttempt::STATE_SENT, 'route', '2026-10-01T00:00:00+00:00', false],
]);
