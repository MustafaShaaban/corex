<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Message;

defined('ABSPATH') || exit;

/**
 * The immutable, validated message a driver delivers.
 */
final class EmailMessage
{
    /**
     * @param list<string>         $to
     * @param list<string>         $cc
     * @param list<string>         $bcc
     * @param array<string,string> $headers
     */
    public function __construct(
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly ?string $replyTo,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $headers = [],
    ) {
    }

    /**
     * @param list<string> $to
     */
    public function withRecipients(array $to): self
    {
        return new self($to, $this->cc, $this->bcc, $this->replyTo, $this->subject, $this->body, $this->headers);
    }
}
