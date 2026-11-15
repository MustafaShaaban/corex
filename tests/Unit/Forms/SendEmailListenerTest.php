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
use Corex\Tests\Support\LanguageOfTheRequest;
use Corex\Tests\Support\MarkedLanguage;

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

    $delivery = (new SendEmailListener(new NotificationDispatcher(new LanguageOfTheRequest(), null, $mailer), emailListenerConfig()))
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

    $delivery = (new SendEmailListener(new NotificationDispatcher(new LanguageOfTheRequest(), $router, null), emailListenerConfig()))
        ->dispatch(submittedEvent());

    expect($delivery->status)->toBe(MailResult::STATE_CAPTURED)
        ->and($router->calls[0]['trigger'])->toBe('forms.contact.submitted')
        ->and($router->calls[0]['context']['submission']['name'])->toBe('Sam');
});

it('records wp_mail acceptance as accepted, never sent, when CoreX Mail is absent', function () {
    Functions\expect('wp_mail')->once()->andReturn(true);

    $delivery = (new SendEmailListener(new NotificationDispatcher(new LanguageOfTheRequest(), null, null), emailListenerConfig()))
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
// Spec 120: drawn in the one frame through the form notification template, with a plain-text
// body for the wp_mail() floor.
it('sends a form other than the contact form its own fields, through the form notification template', function () {
    $mailer = requestKeepingMailer();

    (new SendEmailListener(new NotificationDispatcher(new LanguageOfTheRequest(), null, $mailer), emailListenerConfig()))
        ->dispatch(new FormSubmittedEvent('callback', ['phone' => '01016999700', 'company' => 'Acme']));

    expect($mailer->request->templateName)->toBe('form-notification')
        ->and($mailer->request->subject)->toBe('New "callback" form submission')
        ->and($mailer->request->body)->toBe("phone: 01016999700\ncompany: Acme")
        ->and($mailer->request->context['answers'])->toBe([
            ['label' => 'Phone', 'value' => '01016999700'],
            ['label' => 'Company', 'value' => 'Acme'],
        ]);
});

it('still sends the contact form through the contact template', function () {
    $mailer = requestKeepingMailer();

    (new SendEmailListener(new NotificationDispatcher(new LanguageOfTheRequest(), null, $mailer), emailListenerConfig()))
        ->dispatch(submittedEvent());

    expect($mailer->request->templateName)->toBe('contact-notification');
});

it('tells the submission how its notification went, for whatever reads it after the listeners', function () {
    Functions\expect('wp_mail')->once()->andReturn(false);
    $event = submittedEvent();

    (new SendEmailListener(new NotificationDispatcher(new LanguageOfTheRequest(), null, null), emailListenerConfig()))($event);

    expect($event->delivery()?->status)->toBe(MailResult::STATE_FAILED);
});

/**
 * The subject and the line that stands for an empty submission are CoreX's words. Translated as
 * the listener ran, they were in the language of the request: a notification sent again from
 * the inbox was in the language of whoever pressed the button (DECISIONS #362).
 */
it('has its own words written in the language of the email', function () {
    $language = new MarkedLanguage();
    Functions\when('__')->alias($language->said(...));
    Functions\when('esc_html__')->alias($language->said(...));
    $mailer = requestKeepingMailer();

    (new SendEmailListener(new NotificationDispatcher($language, null, $mailer), emailListenerConfig()))
        ->dispatch(new FormSubmittedEvent('callback', []));

    expect($mailer->request->subject)->toBe('[email] New "callback" form submission')
        ->and($mailer->request->body)->toBe('[email] The submission contained no fields.');
});

/** The site's settled notification style, answered as `get_option()` would. */
function notificationStyleIs(string $style): void
{
    Functions\when('get_option')->alias(static fn (string $option, mixed $default = false): mixed => match ($option) {
        Corex\Forms\Listeners\NotificationStyle::OPTION => $style,
        'admin_email' => 'admin@example.com',
        default => $default,
    });
}

function styledListener(?AttemptingMailer $mailer): SendEmailListener
{
    return new SendEmailListener(
        new NotificationDispatcher(new LanguageOfTheRequest(), null, $mailer),
        emailListenerConfig(),
        style: new Corex\Forms\Listeners\NotificationStyle(),
    );
}

// FR-023: a site that sent code-defined form notifications before spec 120 sends them as it did.
it('FR-023: sends an upgraded site\'s form other than the contact form as before, a finished HTML message', function () {
    notificationStyleIs(Corex\Forms\Listeners\NotificationStyle::AS_BEFORE);
    Functions\when('esc_url')->returnArg();
    $mailer = requestKeepingMailer();

    styledListener($mailer)->dispatch(new FormSubmittedEvent('callback', ['phone' => '01016999700']));

    expect($mailer->request->templateName)->toBeNull()
        ->and($mailer->request->body)->toBe(NotificationDispatcher::htmlBody(['phone' => '01016999700']));
});

it('FR-023: keeps an upgraded site\'s contact form on its template, with the HTML body it had', function () {
    notificationStyleIs(Corex\Forms\Listeners\NotificationStyle::AS_BEFORE);
    $mailer = requestKeepingMailer();

    styledListener($mailer)->dispatch(submittedEvent());

    expect($mailer->request->templateName)->toBe('contact-notification')
        ->and($mailer->request->body)->toBe(NotificationDispatcher::htmlBody(['name' => 'Sam', 'message' => 'Hello']));
});

it('FR-023: hands an upgraded site\'s wp_mail() floor the HTML body it had', function () {
    notificationStyleIs(Corex\Forms\Listeners\NotificationStyle::AS_BEFORE);
    $sent = null;
    Functions\expect('wp_mail')->once()->andReturnUsing(static function (mixed $to, string $subject, string $body) use (&$sent): bool {
        $sent = $body;

        return true;
    });

    styledListener(null)->dispatch(new FormSubmittedEvent('callback', ['phone' => '01016999700']));

    expect($sent)->toBe(NotificationDispatcher::htmlBody(['phone' => '01016999700']));
});

it('FR-018: sends a new site\'s form through the form notification template, with a plain-text floor body', function () {
    notificationStyleIs(Corex\Forms\Listeners\NotificationStyle::DESIGNED);
    $mailer = requestKeepingMailer();

    styledListener($mailer)->dispatch(new FormSubmittedEvent('callback', ['phone' => '01016999700']));

    expect($mailer->request->templateName)->toBe('form-notification')
        ->and($mailer->request->body)->toBe('phone: 01016999700');
});

it('FR-023: sends as before on a site whose style is not settled yet', function () {
    notificationStyleIs('');

    expect((new Corex\Forms\Listeners\NotificationStyle())->designed())->toBeFalse();
});

it('FR-023: settles the style once: as before where the site has submissions, designed where it has none', function () {
    $stored = [];
    Functions\when('get_option')->alias(static function (string $option, mixed $default = false) use (&$stored): mixed {
        return $stored[$option] ?? $default;
    });
    Functions\when('update_option')->alias(static function (string $option, mixed $value) use (&$stored): bool {
        $stored[$option] = $value;

        return true;
    });
    Functions\when('wp_set_option_autoload')->justReturn(true);
    $style = new Corex\Forms\Listeners\NotificationStyle();

    $style->settle(static fn (): bool => true);
    $style->settle(static fn (): bool => false);
    $upgraded = $stored[Corex\Forms\Listeners\NotificationStyle::OPTION];
    $stored = [];
    $style->settle(static fn (): bool => false);

    expect($upgraded)->toBe(Corex\Forms\Listeners\NotificationStyle::AS_BEFORE)
        ->and($stored[Corex\Forms\Listeners\NotificationStyle::OPTION])->toBe(Corex\Forms\Listeners\NotificationStyle::DESIGNED);
});
