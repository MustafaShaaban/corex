<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * An email kept instead of sent, as the gate hands it to the store (spec 113, research R4).
 */
final readonly class CapturedMessage
{
    public const STATE_CAPTURED      = 'captured';
    public const STATE_HELD_FOR_TEST = 'held_for_test';

    /**
     * @param list<string>          $to
     * @param list<string>          $cc
     * @param list<string>          $bcc
     * @param array<string,string>  $headers
     * @param list<string>          $attachments paths of the files the email would carry
     */
    public function __construct(
        public array $to,
        public string $subject,
        public string $body,
        public string $path,
        public string $state = self::STATE_CAPTURED,
        public array $cc = [],
        public array $bcc = [],
        public ?string $replyTo = null,
        public array $headers = [],
        public array $attachments = [],
        public ?string $plainText = null,
    ) {
    }

    public function withState(string $state): self
    {
        return new self(
            $this->to,
            $this->subject,
            $this->body,
            $this->path,
            $state,
            $this->cc,
            $this->bcc,
            $this->replyTo,
            $this->headers,
            $this->attachments,
            $this->plainText,
        );
    }
}
