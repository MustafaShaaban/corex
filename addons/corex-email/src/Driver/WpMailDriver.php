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
use Corex\Support\Config\ConfigInterface;

/**
 * The default driver: delivers via `wp_mail`, honoring whatever SMTP/MTA the site
 * already has. The from-identity comes from the Config engine (`mail.from.*`) and is
 * sanitized before it becomes a header (defense-in-depth alongside the HeaderGuard).
 * Sends HTML email, with a plain-text alternative when the message brings one.
 */
final class WpMailDriver implements MailDriver
{
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

        $alternative = $this->alternative($message->plainText);
        if ($alternative !== null) {
            add_action('phpmailer_init', $alternative);
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
            if ($alternative !== null) {
                remove_action('phpmailer_init', $alternative);
            }
        }
    }

    /**
     * What hands WordPress's mailer the plain-text version, or null when the message has none.
     *
     * `wp_mail()` takes one body. Its mailer sends two as alternatives of one message when it
     * is given the second, and the hook it fires before sending is where that is done.
     */
    private function alternative(?string $plainText): ?Closure
    {
        if ($plainText === null || trim($plainText) === '') {
            return null;
        }

        // It does its work once: `wp_mail()` fires other hooks before it returns, and an email
        // one of their listeners sends must not leave with this one's words.
        //
        // It does not take itself off the hook as it runs, which is how it was first written.
        // WordPress then skips whatever listens at the next priority, where something
        // listens at an earlier one: on a site whose mail server is set up by a listener at
        // priority 20, behind another plugin's at 5, a reply left without the mail server's
        // settings. The caller takes it off, when `wp_mail()` has returned.
        $done = false;

        return static function (object $mailer) use ($plainText, &$done): void {
            if ($done) {
                return;
            }
            $done = true;
            $mailer->AltBody = $plainText;
        };
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
