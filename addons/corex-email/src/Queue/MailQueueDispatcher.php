<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Mail\MailRequest;

/**
 * Hands a mail request to a background queue. The interface keeps QueuedMailer free of
 * any concrete scheduler, so the queue decision is unit-testable; the Action Scheduler
 * implementation is the only piece that touches the scheduler functions.
 */
interface MailQueueDispatcher
{
    /**
     * Whether a queue backend is actually available right now.
     */
    public function available(): bool;

    /**
     * Schedule the request for asynchronous delivery.
     */
    public function enqueue(MailRequest $request): void;
}
