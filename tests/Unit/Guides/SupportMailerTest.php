<?php

/**
 * Unit tests for the Guides support transport ladder (spec 087, FR-006 / FR-007).
 *
 * The contract: a support message leaves whether or not Corex Mail is installed, and the answer the
 * screen is given is the answer the transport actually gave. "Sent" for something that was not sent
 * is the failure being guarded against — a help form is the last screen that should lie.
 *
 * @package Corex\Tests\Unit\Guides
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Guides\Support\SupportDelivery;
use Corex\Guides\Support\SupportMailer;
use Corex\Guides\Support\SupportMessage;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\Mailer;
use Corex\Support\Uuid;

beforeEach(function () {
    Functions\when('is_email')->alias(
        static fn (string $value): string|false => filter_var($value, FILTER_VALIDATE_EMAIL) ?: false,
    );
    Functions\when('__')->returnArg();
    Functions\when('add_action')->justReturn(true);
    Functions\when('remove_action')->justReturn(true);
});

/**
 * One support message. The mailer's job is choosing a transport and a rendering, so the message is
 * a fixture here rather than the thing under test — {@see SupportRequestTemplateTest} covers what it
 * renders to.
 */
function supportFixture(string $replyTo = '', string $message = 'B'): SupportMessage
{
    return new SupportMessage(
        categoryLabel: 'A question',
        message: $message,
        replyTo: $replyTo,
        senderName: 'Reader',
        siteName: 'Example Site',
        siteUrl: 'https://example.test',
    );
}

/** A plain Mailer: best-effort and void by contract, so it records rather than reports. */
function recordingMailer(): Mailer
{
    return new class () implements Mailer {
        /** @var list<MailRequest> */
        public array $sent = [];

        public function send(MailRequest $request): void
        {
            $this->sent[] = $request;
        }
    };
}

function attemptingMailer(string $state): AttemptingMailer
{
    return new class ($state) implements AttemptingMailer {
        public function __construct(private readonly string $state)
        {
        }

        public function send(MailRequest $request): void
        {
        }

        public function attempt(MailRequest $request): MailResult
        {
            return new MailResult(
                attemptId: Uuid::v4(),
                requestId: $request->requestId,
                state: $this->state,
                provider: 'test',
                message: 'test',
                occurredAt: new DateTimeImmutable('now'),
                retryable: false,
            );
        }
    };
}

it('falls through to wp_mail when no mailer is bound', function () {
    $captured = [];
    Functions\when('wp_mail')->alias(
        static function (...$args) use (&$captured): bool {
            $captured = $args;

            return true;
        },
    );

    $delivery = (new SupportMailer())->send(
        'support@example.test',
        supportFixture('reader@example.test'),
    );

    expect($delivery->sent)->toBeTrue()
        ->and($captured[0])->toBe('support@example.test')
        ->and($captured[3])->toBe(['Reply-To: reader@example.test']);
});

/**
 * The rung that matters most: `wp_mail` returning false is the ordinary "the SMTP host refused it"
 * case, and reporting it as success would leave somebody believing a person had their message.
 */
it('reports a wp_mail refusal as not sent', function () {
    Functions\when('wp_mail')->justReturn(false);

    $delivery = (new SupportMailer())->send('support@example.test', supportFixture());

    expect($delivery->sent)->toBeFalse()
        ->and($delivery->reason)->toBe(SupportDelivery::REASON_TRANSPORT);
});

it('prefers a bound mailer over wp_mail', function () {
    Functions\when('wp_mail')->justReturn(false);
    $mailer = recordingMailer();

    $delivery = (new SupportMailer($mailer))->send('support@example.test', supportFixture('reader@example.test'));

    expect($delivery->sent)->toBeTrue()
        ->and($mailer->sent)->toHaveCount(1)
        ->and($mailer->sent[0]->to)->toBe(['support@example.test'])
        ->and($mailer->sent[0]->replyTo)->toBe('reader@example.test');
});

it('keeps the real result when the bound mailer reports one', function (string $state, bool $sent) {
    $delivery = (new SupportMailer(attemptingMailer($state)))->send('support@example.test', supportFixture());

    expect($delivery->sent)->toBe($sent);
})->with([
    'accepted' => [MailResult::STATE_ACCEPTED, true],
    'sent'     => [MailResult::STATE_SENT, true],
    'queued'   => [MailResult::STATE_QUEUED, true],
    'failed'   => [MailResult::STATE_FAILED, false],
    'rejected' => [MailResult::STATE_REJECTED, false],
    'bounced'  => [MailResult::STATE_BOUNCED, false],
]);

/**
 * A Reply-To is a mail header, and an unvalidated one is where header injection lives. Dropping the
 * address is the right trade: losing a reply route beats refusing to carry the message, and beats
 * carrying an attacker's extra headers.
 */
it('drops a reply address that would not validate rather than passing it into a header', function () {
    $mailer = recordingMailer();

    (new SupportMailer($mailer))->send(
        'support@example.test',
        supportFixture("reader@example.test\r\nBcc: everyone@example.test"),
    );

    expect($mailer->sent[0]->replyTo)->toBeNull();
});

it('refuses outright when there is no recipient to send to', function () {
    $mailer = recordingMailer();

    $delivery = (new SupportMailer($mailer))->send('', supportFixture());

    expect($delivery->sent)->toBeFalse()
        ->and($delivery->reason)->toBe(SupportDelivery::REASON_NO_RECIPIENT)
        ->and($mailer->sent)->toBeEmpty();
});

/**
 * `Mailer::send()` is documented as best-effort and must not throw. This covers the case where an
 * implementation does anyway — a help form showing a fatal is the worst version of this screen.
 */
it('survives a mailer that throws despite its contract', function () {
    $thrower = new class () implements Mailer {
        public function send(MailRequest $request): void
        {
            throw new RuntimeException('transport exploded');
        }
    };

    $delivery = (new SupportMailer($thrower))->send('support@example.test', supportFixture());

    expect($delivery->sent)->toBeFalse()
        ->and($delivery->reason)->toBe(SupportDelivery::REASON_TRANSPORT);
});

/**
 * The behaviour spec 093 exists for.
 *
 * `WpMailDriver` stamps `Content-Type: text/html` on everything, so the `"\n"`-joined plain text
 * this used to send through Corex Mail arrived as one run-on paragraph. The floor is the opposite —
 * `wp_mail()` with no Content-Type is read as `text/plain`, where those newlines are real. So the
 * rendering has to follow the transport, not be one string for both.
 */
it('renders through the template on the mailer rung and as plain text on the floor', function () {
    $mailer = recordingMailer();

    (new SupportMailer($mailer, 'corex-guides-support'))->send(
        'support@example.test',
        supportFixture('reader@example.test'),
    );

    expect($mailer->sent[0]->templateName)->toBe('corex-guides-support')
        ->and($mailer->sent[0]->context)->toHaveKey('support')
        ->and($mailer->sent[0]->context['support']['message'])->toBe('B');
});

/**
 * A template name is passed only when the provider could actually register it. Asking the renderer
 * for a template that is not there yields an *empty message*, silently — so a bound mailer with no
 * template must keep sending the plain-text body rather than nothing at all.
 */
it('sends the plain-text body when no template is available, even with a mailer bound', function () {
    $mailer = recordingMailer();

    (new SupportMailer($mailer))->send('support@example.test', supportFixture());

    expect($mailer->sent[0]->templateName)->toBeNull()
        ->and($mailer->sent[0]->body)->toContain('A CoreX Guides reader sent this from the admin.');
});

/**
 * The floor never names a template, whatever the provider decided — `wp_mail()` cannot render one.
 */
it('always sends plain text through the wp_mail floor', function () {
    $sent = [];
    Functions\when('wp_mail')->alias(static function (...$args) use (&$sent): bool {
        $sent[] = $args;

        return true;
    });

    (new SupportMailer(null, 'corex-guides-support'))->send(
        'support@example.test',
        supportFixture(),
    );

    expect($sent[0][2])->toContain('About: A question')
        ->and($sent[0][2])->toContain("\n");
});
