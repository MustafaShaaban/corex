<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Closure;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\EmailLanguage;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;
use Corex\Mail\RoutedMailer;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * Sends a submission notification through the best transport available, with a wp_mail() floor.
 *
 * The detect-and-defer ladder — RoutedMailer → AttemptingMailer → Mailer → wp_mail() — used to
 * live only in the legacy event listener; the flow path had nothing below RoutedMailer, so a site
 * with CoreX Mail inactive saved the submission and sent no notification at all, silently. This
 * service is that ladder, extracted and shared, so both paths reach the floor.
 *
 * It never throws: a transport failure is a `failed`/`not_attempted` {@see NotificationDelivery},
 * never an exception that could roll back an already-saved submission (FR-013).
 *
 * The mailers are injected as nullable collaborators (resolved conditionally by the provider),
 * not looked up from a container — domain logic holds no service locator (Principle IV).
 *
 * It is also where a notification's own words are written: a caller hands over what writes
 * them, and they are written in the email's language ({@see EmailLanguage}).
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private EmailLanguage $language,
        private ?RoutedMailer $routed = null,
        private ?Mailer $mailer = null,
        /** Names the notification for the Email Studio log (spec 113). */
        private ?MailPath $paths = null,
    ) {
    }

    /**
     * @param array<string,mixed>   $context Merge/routing data for the CoreX Mail path.
     * @param Closure():MailRequest $write Writes the notification the rungs below the router
     *                                       send. Asked only when one of them sends, and asked
     *                                       in the email's language.
     */
    public function dispatch(string $trigger, array $context, Closure $write): NotificationDelivery
    {
        $send = fn (): NotificationDelivery => $this->send($trigger, $context, $write);

        return $this->paths === null ? $send() : $this->paths->during(__('Form notification', 'corex'), $send);
    }

    /**
     * @param array<string,mixed>   $context
     * @param Closure():MailRequest $write
     */
    private function send(string $trigger, array $context, Closure $write): NotificationDelivery
    {
        // Rung 1 — CoreX Mail's trigger router. A null return means "no route matched"; fall through.
        if ($this->routed !== null) {
            $result = $this->routed->dispatch($trigger, $context);
            if ($result !== null) {
                return NotificationDelivery::fromResult($result);
            }
        }

        // The notification is written here, and not by whoever asked for it to be sent, so that
        // there is one place where its words are made: a subject translated by the caller was
        // in the language of the request, which for a resend, a flow's test send or any other
        // request from the admin is the signed-in person's own (DECISIONS #362).
        $fallback = $this->language->during($write);

        // Rung 2 — a bound mailer with (or without) a truthful result.
        if ($this->mailer !== null) {
            if ($this->mailer instanceof AttemptingMailer) {
                return NotificationDelivery::fromResult($this->mailer->attempt($fallback));
            }
            $this->mailer->send($fallback);

            return NotificationDelivery::fromResult(new MailResult(
                attemptId: Uuid::v4(),
                requestId: $fallback->requestId,
                state: MailResult::STATE_ACCEPTED,
                provider: 'mailer',
                message: __('The configured mailer accepted the notification without a delivery result.', 'corex'),
                occurredAt: new DateTimeImmutable('now'),
                retryable: false,
            ));
        }

        // Floor — WordPress's own mail path. This is the rung the flow path was missing.
        return $this->wpMail($fallback);
    }

    private function wpMail(MailRequest $fallback): NotificationDelivery
    {
        $recipient = $fallback->to[0] ?? '';
        if ($recipient === '') {
            return NotificationDelivery::notAttempted(
                'no_recipient',
                __('No notification recipient was configured.', 'corex'),
            );
        }

        // Capture a safe wp_mail failure reason without exposing transport internals.
        $failure = '';
        $capture = static function ($error) use (&$failure): void {
            if ($error instanceof \WP_Error) {
                $failure = (string) $error->get_error_message();
            }
        };
        add_action('wp_mail_failed', $capture);
        $accepted = wp_mail($recipient, (string) $fallback->subject, (string) $fallback->body);
        remove_action('wp_mail_failed', $capture);

        return NotificationDelivery::wpMail($accepted, Uuid::v4(), $accepted ? '' : $this->safeFailure($failure));
    }

    /**
     * The plain-text body for a fallback notification: one `label: value` line per field. Shared
     * by both callers of the ladder so the floor format lives in one place.
     *
     * @param array<string,mixed> $values
     */
    public static function plainTextBody(array $values): string
    {
        $lines = [];
        foreach ($values as $name => $value) {
            $lines[] = sprintf('%s: %s', $name, self::scalarize($value));
        }

        // An email with no words in it reads as a fault; it says what it is (spec 120).
        return $lines === [] ? __('The submission contained no fields.', 'corex') : implode("\n", $lines);
    }

    /**
     * The same answers, marked up, for the transports that declare `text/html`.
     *
     * {@see plainTextBody()} separates fields with newlines, and every CoreX mail transport sets
     * `Content-Type: text/html`. HTML collapses whitespace, so the notification arrived as one
     * unbroken run of text — every field on a single line, in an email whose header promised
     * markup. Reported from a real build (issue #138, item 4).
     *
     * Kept as a second method rather than a change to the first: a genuinely plain-text transport
     * would still want the plain-text form, and silently returning HTML from a method named
     * `plainTextBody` is how the mismatch happened in the first place.
     *
     * @param array<string,mixed> $values
     */
    public static function htmlBody(array $values): string
    {
        $rows = '';
        foreach ($values as $name => $value) {
            $rows .= sprintf(
                '<tr><th align="left" style="padding:4px 12px 4px 0;vertical-align:top">%s</th>'
                . '<td style="padding:4px 0;vertical-align:top">%s</td></tr>',
                esc_html(self::humanize((string) $name)),
                nl2br(esc_html(self::scalarize($value))),
            );
        }

        return $rows === ''
            ? '<p>' . esc_html__('The submission contained no fields.', 'corex') . '</p>'
            : '<table role="presentation" cellpadding="0" cellspacing="0">' . $rows . '</table>';
    }

    /**
     * Every answer with a label made from its field's name, in the order given: what a
     * notification's table and a template's "All answers" block list (spec 120).
     *
     * @param array<string,mixed> $values
     *
     * @return list<array{label:string,value:string}>
     */
    public static function answers(array $values): array
    {
        $answers = [];
        foreach ($values as $name => $value) {
            $answers[] = ['label' => self::humanize((string) $name), 'value' => self::scalarize($value)];
        }

        return $answers;
    }

    private static function humanize(string $name): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', $name));
    }

    private static function scalarize(mixed $value): string
    {
        return is_array($value) ? (string) wp_json_encode($value) : (string) $value;
    }

    private function safeFailure(string $raw): string
    {
        // A wp_mail_failed message can name hosts or addresses; keep only a generic, safe line
        // unless the message is short and clearly non-sensitive.
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > 160 || preg_match('/@|password|smtp|host|:\d/i', $raw) === 1) {
            return __('WordPress could not send the notification.', 'corex');
        }

        return $raw;
    }
}
