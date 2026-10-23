<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Capture;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;
use Corex\Mail\Capture\CapturedMessage;
use Corex\Mail\Capture\MailCaptureStore;

/**
 * Keeps what the mail gate captures or holds in Email Studio's capture log (spec 113, R4).
 */
final readonly class CaptureStoreAdapter implements MailCaptureStore
{
    public function __construct(private CapturedEmailRepository $captures)
    {
    }

    public function keep(CapturedMessage $message): string
    {
        $email = new EmailMessage(
            $message->to,
            $message->cc,
            $message->bcc,
            $message->replyTo,
            $message->subject,
            $message->body,
            $message->headers,
            attachments: $message->attachments,
            plainText: $message->plainText,
        );

        return $this->captures->capture($email, path: $message->path, state: $message->state)->captureId;
    }
}
