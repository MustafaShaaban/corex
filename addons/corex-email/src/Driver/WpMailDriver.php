<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Driver;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;
use Corex\Support\Config\ConfigInterface;

/**
 * The default driver: delivers via `wp_mail`, honoring whatever SMTP/MTA the site
 * already has. The from-identity comes from the Config engine (`mail.from.*`) and is
 * sanitized before it becomes a header (defense-in-depth alongside the HeaderGuard).
 * Sends HTML email.
 */
final class WpMailDriver implements MailDriver
{
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function send(EmailMessage $message): bool
    {
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $from = $this->fromHeader();
        if ($from !== '') {
            $headers[] = 'From: ' . $from;
        }

        if ($message->replyTo !== null && $message->replyTo !== '') {
            $headers[] = 'Reply-To: ' . $message->replyTo;
        }

        foreach ($message->cc as $cc) {
            $headers[] = 'Cc: ' . $cc;
        }

        foreach ($message->bcc as $bcc) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        return wp_mail($message->to, $message->subject, $message->body, $headers);
    }

    private function fromHeader(): string
    {
        $name    = sanitize_text_field((string) $this->config->get('mail.from.name', ''));
        $address = sanitize_email((string) $this->config->get('mail.from.address', ''));

        if ($address === '') {
            return '';
        }

        return $name !== '' ? sprintf('%s <%s>', $name, $address) : $address;
    }
}
