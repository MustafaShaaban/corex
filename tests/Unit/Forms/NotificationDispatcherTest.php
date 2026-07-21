<?php

/**
 * Unit tests for the notification dispatch ladder (spec 071 US2: FR-016, FR-017, FR-015).
 *
 * The ladder is RoutedMailer → AttemptingMailer → Mailer → wp_mail(). The gap this closes: the
 * flow path had no wp_mail floor, so a site with CoreX Mail inactive sent nothing at all. These
 * tests pin each rung and prove the floor is reached when nothing above it is bound.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_json_encode')->alias(static fn (mixed $d): string => (string) json_encode($d));
    Functions\when('add_action')->justReturn(true);
    Functions\when('remove_action')->justReturn(true);
});

function fallbackRequest(): MailRequest
{
    return new MailRequest(to: ['admin@example.com'], subject: 'New submission', body: 'A message.');
}

function okResult(string $state = MailResult::STATE_QUEUED): MailResult
{
    return new MailResult(
        attemptId: '11111111-1111-4111-8111-111111111111',
        requestId: '22222222-2222-4222-8222-222222222222',
        state: $state,
        provider: 'corex-mail',
        message: 'ok',
        occurredAt: new DateTimeImmutable('now'),
        retryable: false,
    );
}

it('routes through CoreX Mail when a routed mailer is bound and returns a result', function () {
    $routed = new class implements RoutedMailer {
        public ?string $seenTrigger = null;

        public function dispatch(string $trigger, array $context): ?MailResult
        {
            $this->seenTrigger = $trigger;
            return okResult(MailResult::STATE_QUEUED);
        }
    };

    $delivery = (new NotificationDispatcher($routed, null))
        ->dispatch('forms.contact.submitted', ['x' => 1], fallbackRequest());

    expect($routed->seenTrigger)->toBe('forms.contact.submitted')
        ->and($delivery->status)->toBe(MailResult::STATE_QUEUED)
        ->and($delivery->provider)->toBe('corex-mail');
});

it('falls past a routed mailer that declines (returns null) to the next rung', function () {
    $routed = new class implements RoutedMailer {
        public function dispatch(string $trigger, array $context): ?MailResult
        {
            return null; // no route matched
        }
    };
    $attempting = new class implements AttemptingMailer {
        public function send(MailRequest $request): void
        {
        }

        public function attempt(MailRequest $request): MailResult
        {
            return okResult(MailResult::STATE_CAPTURED);
        }
    };

    $delivery = (new NotificationDispatcher($routed, $attempting))->dispatch('forms.contact.submitted', [], fallbackRequest());

    expect($delivery->status)->toBe(MailResult::STATE_CAPTURED);
});

it('uses the wp_mail floor when nothing above it is bound, mapping true to accepted', function () {
    // This is the case that produced NOTHING before this feature: no CoreX Mail, flow path.
    $sent = [];
    Functions\when('wp_mail')->alias(function ($to, $subject, $body) use (&$sent): bool {
        $sent[] = compact('to', 'subject', 'body');
        return true;
    });

    $delivery = (new NotificationDispatcher(null, null))
        ->dispatch('forms.contact.submitted', [], fallbackRequest());

    expect($sent)->toHaveCount(1)
        ->and($delivery->status)->toBe(MailResult::STATE_ACCEPTED)  // accepted, never sent (FR-015)
        ->and($delivery->provider)->toBe('wp-mail');
});

it('records a retryable failure when wp_mail refuses the message', function () {
    Functions\when('wp_mail')->justReturn(false);

    $delivery = (new NotificationDispatcher(null, null))
        ->dispatch('forms.contact.submitted', [], fallbackRequest());

    expect($delivery->status)->toBe(MailResult::STATE_FAILED)
        ->and($delivery->retryable)->toBeTrue();
});

it('never throws when the wp_mail floor has no recipient — records not_attempted', function () {
    $delivery = (new NotificationDispatcher(null, null))
        ->dispatch('forms.contact.submitted', [], new MailRequest(to: [], subject: 'x', body: 'y'));

    expect($delivery->status)->toBe(\Corex\Forms\Submission\NotificationDelivery::STATUS_NOT_ATTEMPTED)
        ->and($delivery->reasonCode)->toBe('no_recipient');
});
