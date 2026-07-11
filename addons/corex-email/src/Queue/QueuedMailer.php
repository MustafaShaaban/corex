<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Mail\AttemptingMailer;
use Corex\Mail\MailResult;
use Corex\Support\Uuid;
use DateTimeImmutable;

/**
 * A Mailer decorator that queues a send when the gate allows (Action Scheduler present
 * AND the `mail_queue` flag on), and otherwise delegates to the wrapped engine for an
 * immediate send. Bulk paths (e.g. newsletter publish) get queuing for free; everything
 * else, and any install without the backend/flag, sends inline exactly as before. Like
 * the seam it implements, send() never throws.
 */
final class QueuedMailer implements AttemptingMailer
{
    public function __construct(
        private readonly Mailer $inner,
        private readonly MailQueueGate $gate,
        private readonly MailQueueDispatcher $dispatcher,
    ) {
    }

    public function send(MailRequest $request): void
    {
        $this->attempt($request);
    }

    public function attempt(MailRequest $request): MailResult
    {
        if ($this->gate->shouldQueue($this->dispatcher->available())) {
            $this->dispatcher->enqueue($request);

            return new MailResult(
                attemptId: Uuid::v4(),
                requestId: $request->requestId,
                state: MailResult::STATE_QUEUED,
                provider: 'action-scheduler',
                message: __('The mail attempt was queued.', 'corex'),
                occurredAt: new DateTimeImmutable('now'),
                retryable: false,
                parentAttemptId: $request->parentAttemptId,
            );
        }

        if ($this->inner instanceof AttemptingMailer) {
            return $this->inner->attempt($request);
        }

        $this->inner->send($request);

        return new MailResult(
            attemptId: Uuid::v4(),
            requestId: $request->requestId,
            state: MailResult::STATE_ACCEPTED,
            provider: 'legacy-mailer',
            message: __('The legacy mailer accepted the request without a delivery result.', 'corex'),
            occurredAt: new DateTimeImmutable('now'),
            retryable: false,
            parentAttemptId: $request->parentAttemptId,
        );
    }
}
