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
        /**
         * The mailbox this one message should be sent from, or null for the configured default
         * (#150).
         *
         * Last, and nullable, so every existing positional caller is untouched — this is additive.
         *
         * The address only, never the display name. The name is the brand and is the same whichever
         * mailbox a message leaves from; a per-message display name would be a different and more
         * dangerous feature, since a sender name that varies per message is how a spoof reads.
         */
        public readonly ?string $from = null,
        /**
         * Attachment ids, resolved to paths at the driver (spec 081, FR-010).
         *
         * Ids rather than paths, all the way through: a message that carried a path could carry
         * one a request supplied, and `wp_mail()` would happily read it.
         *
         * @var list<int>
         */
        public readonly array $attachments = [],
        /**
         * The same words as plain text, sent beside the HTML as its alternative (spec 106,
         * FR-013). Null for a message that has none: it is sent as HTML alone, as before.
         */
        public readonly ?string $plainText = null,
        /**
         * The name this one message is sent under, or null for the configured one (spec 106,
         * story 5).
         *
         * Not for a caller to choose per message, which is why `$from` above is an address
         * alone: a sender's name that varies with the message is how a spoof reads. This is
         * for a name an administrator set for one kind of message, a reply from the inbox:
         * the only code of CoreX's that fills it reads a setting, through {@see SenderName}.
         * Nothing stops other code filling it, so the mail service refuses one with a line
         * break in it and `WpMailDriver` cleans it again; a driver of a site's own has to.
         */
        public readonly ?string $fromName = null,
    ) {
    }

    /**
     * This message with headers added to its own, the added ones winning.
     *
     * Headers are not written as they are given. A driver writes the ones it knows by name,
     * each checked for what it has to be: see {@see \Corex\Email\Driver\WpMailDriver}.
     *
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        // Every field this class has, by its name, and the headers over them: a field added
        // later is carried without being named here.
        return new self(...[...get_object_vars($this), 'headers' => $headers + $this->headers]);
    }
}
