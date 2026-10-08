<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Mail\MailRequest;

/**
 * A mail request as the scalars and arrays a scheduler can store, and back.
 */
final class MailRequestPayload
{
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
