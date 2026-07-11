<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * Optional trigger-based notification seam implemented by Email Studio.
 */
interface RoutedMailer
{
    /** @param array<string,mixed> $context */
    public function dispatch(string $trigger, array $context): ?MailResult;
}
