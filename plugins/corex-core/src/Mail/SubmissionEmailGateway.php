<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Optional Email Studio operations consumed by the core-neutral Submissions domain.
 */
interface SubmissionEmailGateway
{
    public function reply(string $recipient, string $subject, string $htmlBody): MailResult;

    /** @param array<string,mixed> $context */
    public function resend(string $attemptId, string $recipient, array $context): MailResult;

    /** @return array<string,mixed>|null */
    public function log(string $attemptId): ?array;
}
