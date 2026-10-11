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
    /**
     * What sending a reply from this site would do now: deliver it, keep it, or refuse it.
     *
     * Asked before a reply is written, so the reply box can say so (spec 106, FR-028). It must
     * be the decision {@see reply()} acts on, not a second reading of the same settings.
     */
    public function readiness(): ReplyReadiness;

    public function reply(string $recipient, string $subject, string $htmlBody): MailResult;

    /** @param array<string,mixed> $context */
    public function resend(string $attemptId, string $recipient, array $context): MailResult;

    /** @return array<string,mixed>|null */
    public function log(string $attemptId): ?array;
}
