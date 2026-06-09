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
    ) {
    }

    public function deliver(EmailMessage $message): EmailResult
    {
        $rejection = $this->guard->inspect([
            'subject'  => $message->subject,
            'reply-to' => $message->replyTo ?? '',
        ]);

        if ($rejection !== null) {
            $this->logger->warning(sprintf('Mail rejected: %s', $rejection));

            return $this->result('rejected', $rejection, $message);
        }

        $clean = new EmailMessage(
            $this->valid($message->to),
            $this->valid($message->cc),
            $this->valid($message->bcc),
            $message->replyTo,
            $message->subject,
            $message->body,
            $message->headers,
        );

        if ($clean->to === []) {
            return $this->result('failed', 'No valid recipient.', $clean);
        }

        try {
            $accepted = $this->driver->send($clean);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Mail delivery failed: %s', $e->getMessage()));
            $accepted = false;
        }

        return $this->result($accepted ? 'sent' : 'failed', $accepted ? 'Sent.' : 'Delivery failed.', $clean);
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
