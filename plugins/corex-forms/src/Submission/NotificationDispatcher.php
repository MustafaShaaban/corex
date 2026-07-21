<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Mail\AttemptingMailer;
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
 */
final readonly class NotificationDispatcher
{
    public function __construct(
        private ?RoutedMailer $routed = null,
        private ?Mailer $mailer = null,
    ) {
    }

    /**
     * @param array<string,mixed> $context Merge/routing data for the CoreX Mail path.
     */
    public function dispatch(string $trigger, array $context, MailRequest $fallback): NotificationDelivery
    {
        // Rung 1 — CoreX Mail's trigger router. A null return means "no route matched"; fall through.
        if ($this->routed !== null) {
            $result = $this->routed->dispatch($trigger, $context);
            if ($result !== null) {
                return NotificationDelivery::fromResult($result);
            }
        }

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
            $lines[] = sprintf('%s: %s', $name, is_array($value) ? (string) wp_json_encode($value) : (string) $value);
        }

        return implode("\n", $lines);
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
