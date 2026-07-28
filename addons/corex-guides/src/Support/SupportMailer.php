<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailRequest;
use Corex\Mail\Mailer;
use Throwable;
use WP_Error;

/**
 * Sends one support message, by whatever transport this install actually has (spec 087, FR-006).
 *
 * Two rungs, in order:
 *
 * 1. **A bound `Mailer`.** Only `addons/corex-email` binds one. When it is an {@see AttemptingMailer}
 *    the real delivery result is used; a plain `Mailer` is best-effort by contract and reports
 *    acceptance.
 * 2. **`wp_mail()`.** The floor. Always present, because WordPress is.
 *
 * The `Mailer` is injected as a nullable collaborator, resolved conditionally by the provider —
 * this class holds no container and does not look anything up (Principle IV). It also does not
 * import `Corex\Forms\Submission\NotificationDispatcher`, which is the same ladder one plugin over:
 * corex-forms is optional, and a help form must not stop working because a site does not use forms
 * (Principle IX). The cost is this file; the alternative was a hard dependency on an optional plugin.
 */
final class SupportMailer
{
    public function __construct(private readonly ?Mailer $mailer = null)
    {
    }

    /**
     * @param string $recipient Where the message goes. Already validated by {@see SupportSettings}.
     * @param string $replyTo   The sender's own address, or '' when they have none on file.
     */
    public function send(string $recipient, string $subject, string $body, string $replyTo = ''): SupportDelivery
    {
        if (! is_email($recipient)) {
            return SupportDelivery::failed(SupportDelivery::REASON_NO_RECIPIENT);
        }

        // An address that will not validate is dropped rather than passed on: a malformed Reply-To
        // header is a header-injection surface, and losing the reply address is better than
        // refusing to deliver the message at all.
        $replyTo = is_email($replyTo) ? $replyTo : '';

        $request = new MailRequest(
            to: [$recipient],
            subject: $subject,
            body: $body,
            replyTo: $replyTo !== '' ? $replyTo : null,
        );

        if ($this->mailer !== null) {
            return $this->viaMailer($request);
        }

        return $this->viaWpMail($recipient, $subject, $body, $replyTo);
    }

    private function viaMailer(MailRequest $request): SupportDelivery
    {
        try {
            if ($this->mailer instanceof AttemptingMailer) {
                return $this->mailer->attempt($request)->successful()
                    ? SupportDelivery::sent()
                    : SupportDelivery::failed(SupportDelivery::REASON_TRANSPORT);
            }

            // A plain Mailer's contract is best-effort and void: it does not report, so the honest
            // answer is that it accepted the message, not that it was delivered.
            $this->mailer->send($request);

            return SupportDelivery::sent();
        } catch (Throwable) {
            // The contract says a Mailer must not throw. This catch is here because "must not" is
            // not "cannot", and a help form is the last screen that should show a fatal.
            return SupportDelivery::failed(SupportDelivery::REASON_TRANSPORT);
        }
    }

    private function viaWpMail(string $recipient, string $subject, string $body, string $replyTo): SupportDelivery
    {
        $headers = $replyTo !== '' ? ['Reply-To: ' . $replyTo] : [];

        $failure = '';
        $capture = static function ($error) use (&$failure): void {
            if ($error instanceof WP_Error) {
                $failure = (string) $error->get_error_message();
            }
        };

        add_action('wp_mail_failed', $capture);
        $accepted = wp_mail($recipient, $subject, $body, $headers);
        remove_action('wp_mail_failed', $capture);

        if ($accepted) {
            return SupportDelivery::sent();
        }

        // The transport's own words go to the log, never to the screen: they routinely name the
        // host, the account, and occasionally the credential.
        $this->log($failure);

        return SupportDelivery::failed(SupportDelivery::REASON_TRANSPORT);
    }

    private function log(string $failure): void
    {
        if ($failure === '' || ! (defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- debug-only diagnostic.
        error_log('CoreX Guides support message failed: ' . $failure);
    }
}
