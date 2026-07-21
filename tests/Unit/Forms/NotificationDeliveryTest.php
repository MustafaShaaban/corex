<?php

/**
 * Unit tests for the typed notification-delivery outcome (spec 071 US2/US3: FR-014, FR-015, FR-018, FR-019).
 *
 * The point of this value object is to stop collapsing every mail result into "sent". These tests
 * pin the distinct states, the acceptance-is-not-delivery rule, and the redaction guarantee.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Submission\NotificationDelivery;
use Corex\Mail\MailResult;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function mailResult(string $state, string $provider = 'corex-mail', string $message = 'A safe message.'): MailResult
{
    return new MailResult(
        attemptId: '11111111-1111-4111-8111-111111111111',
        requestId: '22222222-2222-4222-8222-222222222222',
        state: $state,
        provider: $provider,
        message: $message,
        occurredAt: new DateTimeImmutable('2026-07-20T10:00:00+00:00'),
        retryable: $state === MailResult::STATE_FAILED,
    );
}

it('maps each mail-result state onto its own delivery status, never collapsing to sent', function (string $state) {
    $delivery = NotificationDelivery::fromResult(mailResult($state));

    expect($delivery->status)->toBe($state)
        ->and($delivery->attemptId)->toBe('11111111-1111-4111-8111-111111111111')
        ->and($delivery->provider)->toBe('corex-mail')
        ->and($delivery->attemptedAt)->not->toBeNull();
})->with([
    MailResult::STATE_ACCEPTED,
    MailResult::STATE_CAPTURED,
    MailResult::STATE_QUEUED,
    MailResult::STATE_SENT,
    MailResult::STATE_FAILED,
    MailResult::STATE_REJECTED,
]);

it('records a not-attempted delivery with a reason and no attempt id', function () {
    $delivery = NotificationDelivery::notAttempted('no_binding', 'No notification is configured for this form.');

    expect($delivery->status)->toBe('not_attempted')
        ->and($delivery->attemptId)->toBeNull()
        ->and($delivery->attemptedAt)->toBeNull()
        ->and($delivery->reasonCode)->toBe('no_binding')
        ->and($delivery->safeReason)->toBe('No notification is configured for this form.');
});

it('treats wp_mail acceptance as accepted, never as confirmed delivery', function () {
    // FR-015: a transport accepting a message is not proof it reached an inbox.
    $accepted = NotificationDelivery::wpMail(true, '33333333-3333-4333-8333-333333333333');

    expect($accepted->status)->toBe(MailResult::STATE_ACCEPTED)
        ->and($accepted->status)->not->toBe(MailResult::STATE_SENT)
        ->and($accepted->provider)->toBe('wp-mail');
});

it('treats wp_mail rejection as a retryable failure', function () {
    $failed = NotificationDelivery::wpMail(false, '44444444-4444-4444-8444-444444444444', 'Sendmail refused the message.');

    expect($failed->status)->toBe(MailResult::STATE_FAILED)
        ->and($failed->retryable)->toBeTrue()
        ->and($failed->safeReason)->toBe('Sendmail refused the message.');
});

it('hydrates a legacy submission with no delivery record as outcome-unavailable, not success', function () {
    // FR-018: a submission saved before this feature must not read as successful.
    $legacy = NotificationDelivery::unavailable();

    expect($legacy->status)->toBe('unavailable')
        ->and($legacy->attemptId)->toBeNull()
        ->and($legacy->successful())->toBeFalse();
});

it('exposes only safe fields in its persisted projection', function () {
    $delivery = NotificationDelivery::fromResult(mailResult(MailResult::STATE_FAILED, 'wp-mail', 'A safe reason.'));
    $wire = $delivery->toArray();

    expect($wire)->toHaveKeys(['status', 'attempt_id', 'provider', 'attempted_at', 'retryable', 'safe_reason', 'reason_code'])
        ->and($wire)->not->toHaveKey('smtp_host')
        ->and($wire)->not->toHaveKey('credentials');
});

it('round-trips through its array projection', function () {
    $delivery = NotificationDelivery::fromResult(mailResult(MailResult::STATE_CAPTURED));
    $restored = NotificationDelivery::fromArray($delivery->toArray());

    expect($restored->status)->toBe($delivery->status)
        ->and($restored->attemptId)->toBe($delivery->attemptId)
        ->and($restored->provider)->toBe($delivery->provider);
});
