<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * Where a captured or held email is kept. CoreX Mail binds it to Email Studio's capture log;
 * without CoreX Mail nothing is bound, and the gate refuses what it would have kept.
 */
interface MailCaptureStore
{
    /** Keep the message and return the capture's id. */
    public function keep(CapturedMessage $message): string;
}
