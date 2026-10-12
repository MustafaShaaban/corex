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
     * the mail server up on that hook, which are at the usual priority or a little after it.
     * How an email is sent decides how long a header may be, and at the usual priority this
     * read "by `mail()`" on a site whose listener was about to choose a mail server.
     *
     * Not every plugin chooses there. FluentSMTP sets the mailer to `mail()`, fires the hook,
     * and chooses its mail server afterwards: on it this reads `mail()` whatever comes next,
     * which is the careful answer.
     */
    public const AFTER_THE_MAIL_SERVER_IS_SET_UP = 9999;

    /**
     * The header this driver's own email is known by when WordPress's mailer is handed it.
     *
     * `wp_mail()` fires its hooks for every email, and more than one can be on its way at
     * once: a copy sent from the `wp_mail` filter, a notice sent on hearing that an email
     * failed. The first email to reach the hook is not always the one this driver sent, and
     * what it hands the mailer (a plain-text version, an id, the email being answered) must
     * go to that one only. So the email carries a mark of its own as far as the mailer, where
     * it is taken off again: it does not leave the site where the hook is fired.
     */
    private const MARK = 'X-Corex-Send';

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
        $mark = bin2hex(random_bytes(8));
        $mailer = null;
        $prepared = $this->beforeSending($message, $mark, $mailer);
        if ($prepared !== null) {
            $headers[] = self::MARK . ': ' . $mark;
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
                self::takeIdOff($mailer, $message->headers['Message-ID'] ?? null);
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
     * @param string      $mark   What this email is known by at the mailer: {@see MARK}.
     * @param object|null $mailer Set to the mailer once it has been handed this email, for the
     *                            caller to take the id back off it.
     */
    private function beforeSending(EmailMessage $message, string $mark, ?object &$mailer): ?Closure
    {
        $plainText = $message->plainText !== null && trim($message->plainText) !== '' ? $message->plainText : null;
        $messageId = MessageId::isOne($message->headers['Message-ID'] ?? null) ? $message->headers['Message-ID'] : null;
        $answers = MessageId::isOne($message->headers['In-Reply-To'] ?? null) ? $message->headers['In-Reply-To'] : null;
        $before = MessageId::allIn((string) ($message->headers['References'] ?? ''));
        if ($plainText === null && $messageId === null && $answers === null && $before === []) {
            return null;
        }

        // It does not take itself off the hook as it runs, which is how it was first written.
        // WordPress then skips whatever listens at the next priority, where something
        // listens at an earlier one: on a site whose mail server is set up by a listener at
        // priority 20, behind another plugin's at 5, a reply left without the mail server's
        // settings. The caller takes it off, when `wp_mail()` has returned.
        return static function (object $phpmailer) use ($plainText, $messageId, $answers, $before, $mark, &$mailer): void {
            if (! self::isThisDriversEmail($phpmailer, $mark)) {
                // Another email, sent while this one was on its way. If it comes after this
                // one, WordPress has emptied its mailer for it of everything but this one's
                // id, which is taken off here. An id something else gave it is left alone.
                self::takeIdOff($phpmailer, $messageId);

                return;
            }
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
    }

    /**
     * Whether the mailer holds the email this driver marked, taking the mark off it if so.
     *
     * WordPress empties its mailer's headers before each email and writes the email's own, so
     * the mark is there for that email and no other.
     */
    private static function isThisDriversEmail(object $phpmailer, string $mark): bool
    {
        if (! method_exists($phpmailer, 'getCustomHeaders')) {
            return false;
        }
        $headers = (array) $phpmailer->getCustomHeaders();
        $others = array_values(array_filter($headers, static fn (mixed $header): bool => ! (
            is_array($header)
            && strcasecmp(trim((string) ($header[0] ?? '')), self::MARK) === 0
            && trim((string) ($header[1] ?? '')) === $mark
        )));
        if (count($others) === count($headers)) {
            return false;
        }
        $phpmailer->clearCustomHeaders();
        foreach ($others as $header) {
            $phpmailer->addCustomHeader((string) $header[0], (string) ($header[1] ?? ''));
        }

        return true;
    }

    /**
     * Takes an id this driver gave the mailer back off it. Only that one: an id something else
     * gave an email of its own is not this driver's to remove.
     */
    private static function takeIdOff(object $phpmailer, mixed $messageId): void
    {
        if (is_string($messageId) && $messageId !== '' && ($phpmailer->MessageID ?? '') === $messageId) {
            $phpmailer->MessageID = '';
        }
    }

    /**
     * How long a header's value may be before WordPress's mailer rewrites it as an encoded
     * word, which no mail program reads as an id: the email would arrive, and stand alone.
     *
     * Through PHP's own `mail()` that is 63 characters less the 16 the encoding itself takes.
     * Through anything else it is a line of 998. Asked late, after the listeners that choose
     * a mail server on this hook: {@see AFTER_THE_MAIL_SERVER_IS_SET_UP}.
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
