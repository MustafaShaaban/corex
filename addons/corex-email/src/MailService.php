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
use Corex\Support\BootLogger;
use Throwable;

/**
 * Orchestrates a send: deliver the message through the driver and record the
 * outcome. Best-effort and non-fatal — a driver error is caught and logged as
 * `failed`; the service never throws, so a triggering request or event dispatch is
 * never aborted (spec FR-011). The security gate (US2) and recipient resolution
 * (US3) layer onto this pipeline.
 */
final class MailService
{
    public function __construct(
        private readonly MailDriver $driver,
        private readonly EmailLogStore $log,
        private readonly BootLogger $logger,
    ) {
    }

    public function deliver(EmailMessage $message): EmailResult
    {
        try {
            $accepted = $this->driver->send($message);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Mail delivery failed: %s', $e->getMessage()));
            $accepted = false;
        }

        $status = $accepted ? 'sent' : 'failed';

        return new EmailResult($status, $accepted ? 'Sent.' : 'Delivery failed.', $this->log->record($status, $message));
    }
}
