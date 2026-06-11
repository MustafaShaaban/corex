<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;

/**
 * A Mailer decorator that queues a send when the gate allows (Action Scheduler present
 * AND the `mail_queue` flag on), and otherwise delegates to the wrapped engine for an
 * immediate send. Bulk paths (e.g. newsletter publish) get queuing for free; everything
 * else, and any install without the backend/flag, sends inline exactly as before. Like
 * the seam it implements, send() never throws.
 */
final class QueuedMailer implements Mailer
{
    public function __construct(
        private readonly Mailer $inner,
        private readonly MailQueueGate $gate,
        private readonly MailQueueDispatcher $dispatcher,
    ) {
    }

    public function send(MailRequest $request): void
    {
        if ($this->gate->shouldQueue($this->dispatcher->available())) {
            $this->dispatcher->enqueue($request);

            return;
        }

        $this->inner->send($request);
    }
}
