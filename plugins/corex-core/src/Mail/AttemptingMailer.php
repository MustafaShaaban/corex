<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Optional extension of the legacy void Mailer seam for callers that need an outcome.
 */
interface AttemptingMailer extends Mailer
{
    public function attempt(MailRequest $request): MailResult;
}
