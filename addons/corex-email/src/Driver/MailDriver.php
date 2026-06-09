<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Driver;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;

/**
 * Delivers a validated message. Implementations are the boundary to a mail
 * transport (the default uses `wp_mail`); a provider driver is an additive change,
 * never a rewrite. Returns whether the transport accepted the message.
 */
interface MailDriver
{
    public function send(EmailMessage $message): bool;
}
