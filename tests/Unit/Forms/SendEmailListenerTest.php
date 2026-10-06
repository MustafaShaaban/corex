<?php

/**
 * Unit tests for truthful legacy-form notification outcomes with and without CoreX Mail.
 *
 * The listener now delegates the transport ladder to NotificationDispatcher and returns a typed
 * NotificationDelivery. The load-bearing correction: wp_mail acceptance is `accepted`, never
 * `sent` (FR-015) — the previous code mislabelled it.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Forms\Submission\NotificationDispatcher;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('is_email')->alias(static fn (string $v): string|false => str_contains($v, '@') ? $v : false);
    Functions\when('sanitize_email')->returnArg();
    Functions\when('get_bloginfo')->justReturn('CoreX Test');
    Functions\when('wp_json_encode')->alias(static fn (mixed $d): string => (string) json_encode($d));
    Functions\when('add_action')->justReturn(true);
    Functions\when('remove_action')->justReturn(true);
    Functions\when('get_option')->justReturn('admin@example.com');
});

function emailListenerConfig(): ConfigInterface
{
    return new class implements ConfigInterface {
        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'forms.email.recipient' ? 'owner@example.com' : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'forms.email.recipient';
        }
    };
}

function submittedEvent(): FormSubmittedEvent
{
    return new FormSubmittedEvent('contact', ['name' => 'Sam', 'message' => 'Hello']);
}

it('returns the captured outcome from a result-bearing CoreX mailer', function () {
    $mailer = new class implements AttemptingMailer {
        public function send(MailRequest $request): void
        {
        }

        public function attempt(MailRequest $request): MailResult
        {
            return new MailResult(
                attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
                requestId: $request->requestId,
                state: MailResult::STATE_CAPTURED,
                provider: 'corex-mail',
                message: 'Captured.',
                occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
                retryable: false,
            );
        }
    };

    $delivery = (new SendEmailListener(new NotificationDispatcher(null, $mailer), emailListenerConfig()))
        ->dispatch(submittedEvent());

    expect($delivery->status)->toBe(MailResult::STATE_CAPTURED);
});

it('uses an active Email Studio route before the wp_mail floor', function () {
    $router = new class implements RoutedMailer {
        /** @var list<array{trigger:string,context:array<string,mixed>}> */
        public array $calls = [];

        public function dispatch(string $trigger, array $context): ?MailResult
        {
            $this->calls[] = compact('trigger', 'context');

            return new MailResult(
                attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
                requestId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
                state: MailResult::STATE_CAPTURED,
                provider: 'corex-capture',
                message: 'Captured through route.',
                occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
                retryable: false,
            );
        }
    };

    $delivery = (new SendEmailListener(new NotificationDispatcher($router, null), emailListenerConfig()))
        ->dispatch(submittedEvent());

    expect($delivery->status)->toBe(MailResult::STATE_CAPTURED)
        ->and($router->calls[0]['trigger'])->toBe('forms.contact.submitted')
        ->and($router->calls[0]['context']['submission']['name'])->toBe('Sam');
});

it('records wp_mail acceptance as accepted, never sent, when CoreX Mail is absent', function () {
    Functions\expect('wp_mail')->once()->andReturn(true);

    $delivery = (new SendEmailListener(new NotificationDispatcher(null, null), emailListenerConfig()))
        ->dispatch(submittedEvent());

    expect($delivery->status)->toBe(MailResult::STATE_ACCEPTED)
        ->and($delivery->status)->not->toBe(MailResult::STATE_SENT)
        ->and($delivery->provider)->toBe('wp-mail');
});

/**
 * A mailer that keeps the request it is handed, so a test can read what the listener asked for.
 */
function requestKeepingMailer(): AttemptingMailer
{
    return new class implements AttemptingMailer {
        public ?MailRequest $request = null;

        public function send(MailRequest $request): void
        {
            $this->request = $request;
        }

        public function attempt(MailRequest $request): MailResult
        {
            $this->request = $request;

            return new MailResult(
                attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
                requestId: $request->requestId,
                state: MailResult::STATE_CAPTURED,
                provider: 'corex-mail',
                message: 'Captured.',
                occurredAt: new DateTimeImmutable('2026-10-06T10:00:00+00:00'),
                retryable: false,
            );
        }
    };
}

/**
 * Every form's notification named the `contact-notification` template, and a mailer that has
 * templates renders the template in place of the body it is also handed. That template prints a
 * name, an email and a message, so a form with any other field sent an email without it, under
 * the subject "New contact form submission". Reported on 2026-10-06 from a client site.
 */
it('sends a form other than the contact form its own fields, not the contact template', function () {
    $mailer = requestKeepingMailer();

    (new SendEmailListener(new NotificationDispatcher(null, $mailer), emailListenerConfig()))
        ->dispatch(new FormSubmittedEvent('callback', ['phone' => '01016999700', 'company' => 'Acme']));

    expect($mailer->request->templateName)->toBeNull()
        ->and($mailer->request->subject)->toBe('New "callback" form submission')
        ->and($mailer->request->body)->toContain('01016999700')
        ->and($mailer->request->body)->toContain('Acme');
});

it('still sends the contact form through the contact template', function () {
    $mailer = requestKeepingMailer();

    (new SendEmailListener(new NotificationDispatcher(null, $mailer), emailListenerConfig()))
        ->dispatch(submittedEvent());

    expect($mailer->request->templateName)->toBe('contact-notification');
});
