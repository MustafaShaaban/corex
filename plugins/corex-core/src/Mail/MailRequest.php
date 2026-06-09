<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * A primitive, transport-neutral mail request. It carries only scalars and arrays
 * so corex-core (and any consumer, e.g. the Forms add-on) can describe an email
 * without depending on the Corex Mail engine's concrete types. The bound Mailer
 * implementation turns it into a real, validated message.
 */
final class MailRequest
{
    /**
     * @param list<string>        $to
     * @param array<string,mixed> $context merge data for $templateName
     */
    public function __construct(
        public readonly array $to,
        public readonly ?string $templateName = null,
        public readonly array $context = [],
        public readonly ?string $subject = null,
        public readonly ?string $body = null,
        public readonly ?string $replyTo = null,
    ) {
    }
}
