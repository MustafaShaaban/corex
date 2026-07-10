<?php

/**
 * Unit tests for truthful submission notification outcomes with and without CoreX Mail.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Container\Container;
use Corex\Forms\Listeners\SendEmailListener;
use Corex\Forms\Submission\FormSubmittedEvent;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Support\Config\ConfigInterface;

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

it('returns the result from a result-bearing CoreX mailer', function () {
    Functions\when('get_bloginfo')->justReturn('CoreX Test');
    $container = new Container();
    $mailer    = new class implements AttemptingMailer {
        public function send(MailRequest $request): void
        {
            $this->attempt($request);
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
    $container->instance(Mailer::class, $mailer);

    $result = (new SendEmailListener($container, emailListenerConfig()))->dispatch(submittedEvent());

    expect($result->state)->toBe(MailResult::STATE_CAPTURED);
});

it('uses an active Email Studio route before the legacy template path', function () {
    Functions\when('get_bloginfo')->justReturn('CoreX Test');
    $container = new Container();
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
    $container->instance(RoutedMailer::class, $router);

    $result = (new SendEmailListener($container, emailListenerConfig()))->dispatch(submittedEvent());

    expect($result->state)->toBe(MailResult::STATE_CAPTURED)
        ->and($router->calls[0]['trigger'])->toBe('forms.contact.submitted')
        ->and($router->calls[0]['context']['submission']['name'])->toBe('Sam');
});

it('returns the real wp mail fallback outcome when CoreX Mail is absent', function () {
    Functions\when('__')->returnArg();
    Functions\when('get_bloginfo')->justReturn('CoreX Test');
    Functions\expect('wp_mail')->once()->andReturn(true);

    $result = (new SendEmailListener(new Container(), emailListenerConfig()))->dispatch(submittedEvent());

    expect($result->state)->toBe(MailResult::STATE_SENT)
        ->and($result->provider)->toBe('wp-mail');
});
