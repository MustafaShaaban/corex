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
 * The Action Scheduler-backed dispatcher. Enqueues an async action carrying the (scalar/
 * array-only) MailRequest, and processes it on the registered hook by reconstructing the
 * request and sending it through the immediate engine. Action Scheduler ships with
 * WooCommerce and many plugins; when it is absent, `available()` is false and CoreX Mail
 * binds {@see CronMailDispatcher} in its place (never a hard dependency).
 */
final class ActionSchedulerDispatcher implements MailQueueDispatcher
{
    private const GROUP = 'corex-mail';

    public function __construct(private readonly Mailer $immediate)
    {
    }

    public function available(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    public function name(): string
    {
        return 'action-scheduler';
    }

    public function enqueue(MailRequest $request): void
    {
        as_enqueue_async_action(self::HOOK, [MailRequestPayload::toArray($request)], self::GROUP);
    }

    public function handle(array $payload): void
    {
        $this->immediate->send(MailRequestPayload::fromArray($payload));
    }
}
