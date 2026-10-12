<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Driver;

defined('ABSPATH') || exit;

use Closure;
use Corex\Email\Message\AttachmentResolver;
use Corex\Email\Message\EmailMessage;
use Corex\Mail\MessageId;
use Corex\Support\Config\ConfigInterface;

/**
 * The default driver: delivers via `wp_mail`, honoring whatever SMTP/MTA the site
 * already has. The from-identity comes from the Config engine (`mail.from.*`) and is
 * sanitized before it becomes a header (defense-in-depth alongside the HeaderGuard).
 * Sends HTML email, with a plain-text alternative when the message brings one.
 */
final class WpMailDriver implements MailDriver
{
    /**
     * When this driver's own listener runs on `phpmailer_init`: after the listeners that set
     * the mail server up, which are at the usual priority or a little after it. How an email
     * is sent decides how long a header may be, and at the usual priority this read "by
     * `mail()`" on a site about to send through a mail server.
     */
    public const AFTER_THE_MAIL_SERVER_IS_SET_UP = 9999;

    public function __construct(
        private readonly ConfigInterface $config,
        // Defaulted so every existing construction site keeps working; the resolver is stateless.
        private readonly AttachmentResolver $attachments = new AttachmentResolver(),
    ) {
    }

    public function send(EmailMessage $message): bool
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $from = $this->fromHeader($message->from);
        if ($from !== '') {
            $headers[] = 'From: ' . $from;
        }

        $replyTo = ($message->replyTo !== null && $message->replyTo !== '')
            ? $message->replyTo
            : sanitize_email((string) $this->config->get('mail.reply_to', ''));
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        foreach ($message->cc as $cc) {
            $headers[] = 'Cc: ' . $cc;
        }

        foreach ($message->bcc as $bcc) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        // What `wp_mail()` has no argument for: the plain-text version, the id the email is
        // sent under, and which email it answers.
        $mailer = null;
        $prepared = $this->beforeSending($message, $mailer);
        if ($prepared !== null) {
            add_action('phpmailer_init', $prepared, self::AFTER_THE_MAIL_SERVER_IS_SET_UP);
        }

        try {
            // The fifth argument. `wp_mail()` has always taken `$attachments` and this driver
            // has always passed four, so the mail stack could not send a file at all (spec 081,
            // FR-010).
            return wp_mail(
                $message->to,
                $message->subject,
                $message->body,
                $headers,
                $this->attachments->resolve($message->attachments),
            );
        } finally {
            // For this message only.
            if ($prepared !== null) {
                remove_action('phpmailer_init', $prepared, self::AFTER_THE_MAIL_SERVER_IS_SET_UP);
            }
            // WordPress empties the body and the headers of its mailer before each email, and
            // not the id: left there, it would be the id of the next email anything on the
            // site sent, and two emails under one id are one email to a mail program.
            if (is_object($mailer)) {
                $mailer->MessageID = '';
            }
        }
    }

    /**
     * What hands WordPress's mailer what `wp_mail()` has no argument for, or null when the
     * message brings none of it: its plain-text version, the id it is sent under, and which
     * email it answers.
     *
     * `wp_mail()` takes one body. Its mailer sends two as alternatives of one message when it
     * is given the second. It makes an id of its own for every message unless it is given one,
     * which is how a later email could not name this one (spec 106, FR-037). The hook it fires
     * before sending is where all of it is done.
     *
     * The three headers are taken from the message by name and written only as ids: a header
     * taken as it was given could carry a second header behind it, and a recipient nobody sees.
     *
     * @param object|null $mailer Set to the mailer once the hook has run, for the caller to
     *                            take the id back off it.
     */
    private function beforeSending(EmailMessage $message, ?object &$mailer): ?Closure
    {
        $plainText = $message->plainText !== null && trim($message->plainText) !== '' ? $message->plainText : null;
        $messageId = MessageId::isOne($message->headers['Message-ID'] ?? null) ? $message->headers['Message-ID'] : null;
        $answers = MessageId::isOne($message->headers['In-Reply-To'] ?? null) ? $message->headers['In-Reply-To'] : null;
        $before = MessageId::allIn((string) ($message->headers['References'] ?? ''));
        if ($plainText === null && $messageId === null && $answers === null && $before === []) {
            return null;
        }

        // It does its work once: `wp_mail()` fires other hooks before it returns, and an email
        // one of their listeners sends must not leave with this one's words or id.
        //
        // It does not take itself off the hook as it runs, which is how it was first written.
        // WordPress then skips whatever listens at the next priority, where something
        // listens at an earlier one: on a site whose mail server is set up by a listener at
        // priority 20, behind another plugin's at 5, a reply left without the mail server's
        // settings. The caller takes it off, when `wp_mail()` has returned.
        $done = false;
        $once = static function (object $phpmailer) use ($plainText, $messageId, $answers, $before, &$done, &$mailer): void {
            if ($done) {
                // A second email, sent by something listening to how the first one went.
                // WordPress has emptied its mailer for it of everything but the first one's
                // id, which is taken off here: two emails under one id are one email.
                $phpmailer->MessageID = '';

                return;
            }
            $done = true;
            $mailer = $phpmailer;
            if ($plainText !== null) {
                $phpmailer->AltBody = $plainText;
            }
            if ($messageId !== null) {
                $phpmailer->MessageID = $messageId;
            }
            $room = self::roomFor($phpmailer);
            if ($answers !== null && strlen($answers) <= $room) {
                $phpmailer->addCustomHeader('In-Reply-To', $answers);
            }
            $named = self::latestThatFit($before, $room);
            if ($named !== '') {
                $phpmailer->addCustomHeader('References', $named);
            }
        };

        return $once;
    }

    /**
     * How long a header's value may be before WordPress's mailer rewrites it as an encoded
     * word, which no mail program reads as an id: the email would arrive, and stand alone.
     *
     * Through PHP's own `mail()` that is 63 characters less the 16 the encoding itself takes.
     * Through anything else it is a line of 998. Asked when the mail server has been set up
     * by whoever sets it up, which is why this listens late.
     */
    private static function roomFor(object $phpmailer): int
    {
        return ($phpmailer->Mailer ?? 'mail') === 'mail' ? MessageId::FITS : 982;
    }

    /**
     * The latest of the ids that fit in a header together, in order. The one an email answers
     * is the last, and is what a mail program needs; the ones before it are named while there
     * is room.
     *
     * @param list<string> $ids Oldest first.
     */
    private static function latestThatFit(array $ids, int $room): string
    {
        $named = '';
        foreach (array_reverse($ids) as $id) {
            $longer = $named === '' ? $id : $id . ' ' . $named;
            if (strlen($longer) > $room) {
                break;
            }
            $named = $longer;
        }

        return $named;
    }

    /**
     * The From header for one message.
     *
     * SMTP relays route on this address — in FluentSMTP and most relay plugins each configured
     * mailbox is its own authenticated connection — so a site with three properly configured
     * mailboxes could previously still send everything through one of them (#150).
     *
     * The message's own address wins when it carries one and it is a real address. An unusable
     * value falls back to the configured sender rather than emitting a broken header: a message
     * that arrives from the wrong mailbox is a smaller failure than one that does not arrive.
     */
    private function fromHeader(?string $messageFrom = null): string
    {
        $name    = sanitize_text_field((string) $this->config->get('mail.from.name', ''));
        $address = sanitize_email((string) $this->config->get('mail.from.address', ''));

        $requested = $messageFrom === null ? '' : sanitize_email(trim($messageFrom));
        if ($requested !== '' && is_email($requested) !== false) {
            $address = $requested;
        }

        if ($address === '') {
            return '';
        }

        return $name !== '' ? sprintf('%s <%s>', $name, $address) : $address;
    }
}
