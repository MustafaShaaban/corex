<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email;

defined('ABSPATH') || exit;

use Corex\Email\Driver\MailDriver;
use Corex\Email\Log\EmailLogStore;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Security\HeaderGuard;
use Corex\Mail\Capture\CapturedMessage;
use Corex\Mail\Capture\MailDecision;
use Corex\Mail\Capture\MailGate;
use Corex\Mail\Capture\MailPath;
use Corex\Mail\MailResult;
use Corex\Support\BootLogger;
use Throwable;

/**
 * Orchestrates a send: header-injection guard → recipient validation → deliver
 * through the driver → record the outcome. Best-effort and non-fatal — a header
 * rejection, an empty valid-recipient set, or a driver error each short-circuit
 * with a logged result; the service never throws, so a triggering request or event
 * dispatch is never aborted (spec FR-006, FR-007, FR-011).
 */
final class MailService
{
    public function __construct(
        private readonly MailDriver $driver,
        private readonly EmailLogStore $log,
        private readonly HeaderGuard $guard,
        private readonly BootLogger $logger,
        /** Spec 113: development and staging sites keep mail instead of sending it. */
        private readonly ?MailGate $gate = null,
        private readonly ?MailPath $paths = null,
    ) {
    }

    public function deliver(EmailMessage $message): EmailResult
    {
        $rejection = $this->guard->inspect([
            'subject'  => $message->subject,
            'from'     => $message->from ?? '',
            'from name' => $message->fromName ?? '',
            'reply-to' => $message->replyTo ?? '',
        ]);

        if ($rejection !== null) {
            $this->logger->warning(sprintf('Mail rejected: %s', $rejection));

            return $this->result('rejected', $rejection, $message);
        }

        // Every field of the message, and only the recipients changed. This call listed the seven
        // fields the class had when it was written, so the sender and the attachments added after
        // it were dropped here on every send (#150). They are passed by name so that a reordered
        // constructor fails loudly; `MailServiceTest` fails when a field is added and not carried.
        $clean = new EmailMessage(
            $this->valid($message->to),
            $this->valid($message->cc),
            $this->valid($message->bcc),
            $message->replyTo,
            $message->subject,
            $message->body,
            $message->headers,
            from: $message->from,
            attachments: $message->attachments,
            plainText: $message->plainText,
            fromName: $message->fromName,
        );

        if ($clean->to === []) {
            return $this->result('failed', 'No valid recipient.', $clean);
        }

        $decision = $this->gate?->decide(true) ?? MailDecision::Deliver;
        $name = $this->gate === null ? '' : ($this->paths?->current() ?? __('CoreX mail', 'corex'));
        if ($decision !== MailDecision::Deliver) {
            return $this->keep($clean, $name, $decision);
        }

        try {
            // Sent under its name, so the wp_mail() gate knows the email is CoreX's own.
            $accepted = $this->paths === null || $name === ''
                ? $this->driver->send($clean)
                : $this->paths->during($name, fn (): bool => $this->driver->send($clean));
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Mail delivery failed: %s', $e->getMessage()));
            $accepted = false;
        }

        return $this->result($accepted ? 'sent' : 'failed', $accepted ? 'Sent.' : 'Delivery failed.', $clean);
    }

    /**
     * Keep an email the gate did not let go: captured (a terminal success, so no retry sends
     * it), or rejected when it could be kept nowhere.
     */
    private function keep(EmailMessage $email, string $name, MailDecision $decision): EmailResult
    {
        $message = new CapturedMessage(
            to: $email->to,
            subject: $email->subject,
            body: $email->body,
            path: $name,
            cc: $email->cc,
            bcc: $email->bcc,
            replyTo: $email->replyTo,
            headers: $email->headers,
            attachments: array_values(array_filter($email->attachments, 'is_string')),
            plainText: $email->plainText,
        );

        if ($decision === MailDecision::Refuse) {
            $this->gate?->refuse($message);
        }

        // keep() records the refusal itself when the email cannot be kept after all.
        if ($decision === MailDecision::Refuse || $this->gate?->keep($message, $decision) === null) {
            return $this->result('rejected', 'Not sent: this site keeps its mail, and there is nowhere to keep it.', $email);
        }

        return $this->result(MailResult::STATE_CAPTURED, 'Captured: kept in Email Studio, not sent.', $email);
    }

    private function result(string $status, string $message, EmailMessage $email): EmailResult
    {
        return new EmailResult($status, $message, $this->log->record($status, $email));
    }

    /**
     * @param list<string> $addresses
     *
     * @return list<string> only the syntactically valid addresses; invalid ones are dropped + logged
     */
    private function valid(array $addresses): array
    {
        $valid = [];

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) !== false) {
                $valid[] = $address;
            } else {
                $this->logger->warning(sprintf('Dropped invalid recipient: %s', $address));
            }
        }

        return $valid;
    }
}
