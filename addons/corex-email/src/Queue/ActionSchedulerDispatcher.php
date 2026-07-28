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
 * WooCommerce and many plugins; when it is absent, `available()` is false and the gate
 * keeps everything inline (never a hard dependency). The (de)serialization is exposed
 * for headless tests.
 */
final class ActionSchedulerDispatcher implements MailQueueDispatcher
{
    public const HOOK = 'corex_mail_send';
    private const GROUP = 'corex-mail';

    public function __construct(private readonly Mailer $immediate)
    {
    }

    public function available(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    public function enqueue(MailRequest $request): void
    {
        as_enqueue_async_action(self::HOOK, [self::toArray($request)], self::GROUP);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload): void
    {
        $this->immediate->send(self::fromArray($payload));
    }

    /**
     * @return array<string,mixed>
     */
    public static function toArray(MailRequest $request): array
    {
        return [
            'to'           => $request->to,
            'templateName' => $request->templateName,
            'context'      => $request->context,
            'subject'      => $request->subject,
            'body'         => $request->body,
            'replyTo'      => $request->replyTo,
            // Without this a message sent immediately and the same message sent through the
            // queue leave from different addresses — an inconsistency that is very hard to
            // notice and very annoying to debug (#150).
            'from'         => $request->from,
            'attachments'  => $request->attachments,
            'requestId'    => $request->requestId,
            'parentAttemptId' => $request->parentAttemptId,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function fromArray(array $payload): MailRequest
    {
        return new MailRequest(
            to: is_array($payload['to'] ?? null) ? array_values(array_map('strval', $payload['to'])) : [],
            templateName: isset($payload['templateName']) ? (string) $payload['templateName'] : null,
            context: is_array($payload['context'] ?? null) ? $payload['context'] : [],
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            body: isset($payload['body']) ? (string) $payload['body'] : null,
            replyTo: isset($payload['replyTo']) ? (string) $payload['replyTo'] : null,
            from: isset($payload['from']) ? (string) $payload['from'] : null,
            attachments: is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [],
            requestId: isset($payload['requestId']) ? (string) $payload['requestId'] : null,
            parentAttemptId: isset($payload['parentAttemptId']) ? (string) $payload['parentAttemptId'] : null,
        );
    }
}
