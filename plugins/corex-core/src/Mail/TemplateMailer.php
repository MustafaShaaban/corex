<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Optional capability for callers that bind a concrete editable template at runtime.
 */
interface TemplateMailer
{
    /**
     * @param list<string>        $recipients
     * @param array<string,mixed> $context
     */
    public function dispatchTemplate(
        int $templateId,
        array $recipients,
        ?string $replyTo,
        array $context,
    ): ?MailResult;
}
