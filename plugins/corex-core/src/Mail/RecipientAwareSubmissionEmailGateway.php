<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * What a submission email gateway can add for resends (spec 112, User Story 7). Optional: a
 * gateway that implements only {@see SubmissionEmailGateway}, as a client site's may, keeps
 * working; CoreX then resends once per recipient and does not resend an email whose recipients it
 * cannot confirm.
 */
interface RecipientAwareSubmissionEmailGateway extends SubmissionEmailGateway
{
    /**
     * Sends the attempt's email again, once, to all of these addresses.
     *
     * @param list<string>        $recipients
     * @param array<string,mixed> $context
     */
    public function resendTo(string $attemptId, array $recipients, array $context): MailResult;

    /** Whether the attempt was sent to this address. */
    public function wasSentTo(string $attemptId, string $address): bool;
}
