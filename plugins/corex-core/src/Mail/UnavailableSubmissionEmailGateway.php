<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Honest optional-add-on fallback: email actions remain unavailable without Email Studio.
 */
final class UnavailableSubmissionEmailGateway implements SubmissionEmailGateway
{
    public function reply(string $recipient, string $subject, string $htmlBody): MailResult
    {
        throw new DomainException(__('Email Studio is required for submission replies.', 'corex'));
    }

    public function resend(string $attemptId, string $recipient, array $context): MailResult
    {
        throw new DomainException(__('Email Studio is required to resend submission email.', 'corex'));
    }

    public function log(string $attemptId): ?array
    {
        return null;
    }
}
