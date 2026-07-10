<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Capture;

defined('ABSPATH') || exit;

use Corex\Email\Driver\MailDriver;
use Corex\Email\Message\EmailMessage;

/**
 * Mail driver that writes locally and never reaches a transport.
 */
final class CaptureMailDriver implements MailDriver
{
    public function __construct(private readonly CapturedEmailRepository $captures)
    {
    }

    public function send(EmailMessage $message): bool
    {
        $this->captures->capture($message);

        return true;
    }
}
