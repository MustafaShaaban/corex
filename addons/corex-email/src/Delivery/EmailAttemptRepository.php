<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Delivery;

defined('ABSPATH') || exit;

use Corex\Email\Studio\EmailStudioStore;
use DateTimeImmutable;
use DomainException;

/**
 * Append-only provider-event repository keyed by attempt correlation ID.
 */
final class EmailAttemptRepository
{
    private const TYPE = 'email_attempt';

    public function __construct(private readonly EmailStudioStore $store)
    {
    }

    public function record(EmailAttempt $attempt): EmailAttempt
    {
        if ($attempt->id !== 0 || $this->findByAttemptId($attempt->attemptId) !== null) {
            throw new DomainException(__('Email attempts are immutable and correlation IDs must be unique.', 'corex'));
        }

        $attempt = $attempt->withRecipientEvidence(
            $this->redact($attempt->recipient),
            $this->recipientHash($attempt->recipient),
        );
        $id = $this->store->create(self::TYPE, $attempt->attemptId, $attempt->subject ?: __('(no subject)', 'corex'), 0, [
            'attempt_id'        => $attempt->attemptId,
            'uuid'              => $attempt->attemptId,
            'request_id'        => $attempt->requestId,
            'parent_attempt_id' => $attempt->parentAttemptId,
            'recipient'         => $attempt->recipient,
            'recipient_hash'    => $attempt->recipientHash,
            'subject'           => $attempt->subject,
            'template_slug'     => $attempt->templateSlug,
            'template_id'       => $attempt->templateId,
            'template_version'  => $attempt->templateVersion,
            'route_id'          => $attempt->routeId,
            'state'             => $attempt->state,
            'provider'          => $attempt->provider,
            'provider_event'    => $attempt->providerEvent,
            'provider_message_id' => $attempt->providerMessageId,
            'error_code'        => $attempt->errorCode,
            'retryable'         => $attempt->retryable,
            'occurred_at'       => $attempt->occurredAt->format(DATE_ATOM),
            'created_at'        => $attempt->occurredAt->format(DATE_ATOM),
            'updated_at'        => $attempt->occurredAt->format(DATE_ATOM),
            'source'            => $attempt->source,
            'environment'       => $attempt->environment,
        ]);

        return $attempt->withId($id);
    }

    public function findByAttemptId(string $attemptId): ?EmailAttempt
    {
        $record = $this->store->findBySlug(self::TYPE, $attemptId);

        return $record === null ? null : $this->attempt($record);
    }

    /** @return list<EmailAttempt> */
    public function latest(int $limit = 50): array
    {
        $limit   = max(1, min(100, $limit));
        $records = array_slice(array_reverse($this->store->all(self::TYPE)), 0, $limit);

        return array_map($this->attempt(...), $records);
    }

    /** @param array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>} $record */
    private function attempt(array $record): EmailAttempt
    {
        $payload = $record['payload'];

        return new EmailAttempt(
            id: $record['id'],
            attemptId: (string) ($payload['uuid'] ?? $payload['attempt_id'] ?? ''),
            requestId: (string) ($payload['request_id'] ?? ''),
            parentAttemptId: is_string($payload['parent_attempt_id'] ?? null) ? $payload['parent_attempt_id'] : null,
            recipient: (string) ($payload['recipient'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            templateSlug: is_string($payload['template_slug'] ?? null) ? $payload['template_slug'] : null,
            state: (string) ($payload['state'] ?? ''),
            provider: (string) ($payload['provider'] ?? ''),
            providerEvent: is_string($payload['provider_event'] ?? null) ? $payload['provider_event'] : null,
            retryable: (bool) ($payload['retryable'] ?? false),
            occurredAt: new DateTimeImmutable((string) ($payload['created_at'] ?? $payload['occurred_at'] ?? '')),
            source: (string) ($payload['source'] ?? 'application'),
            recipientHash: is_string($payload['recipient_hash'] ?? null) ? $payload['recipient_hash'] : null,
            environment: (string) ($payload['environment'] ?? 'production'),
            templateId: is_int($payload['template_id'] ?? null) ? $payload['template_id'] : null,
            templateVersion: is_int($payload['template_version'] ?? null) ? $payload['template_version'] : null,
            routeId: is_int($payload['route_id'] ?? null) ? $payload['route_id'] : null,
            providerMessageId: is_string($payload['provider_message_id'] ?? null) ? $payload['provider_message_id'] : null,
            errorCode: is_string($payload['error_code'] ?? null) ? $payload['error_code'] : null,
        );
    }

    private function redact(string $recipient): string
    {
        [$local, $domain] = explode('@', $recipient, 2);
        $visible = mb_substr($local, 0, 1);

        return $visible . '***@' . $domain;
    }

    private function recipientHash(string $recipient): string
    {
        $normalized = strtolower(trim($recipient));
        $key = function_exists('wp_salt') ? wp_salt('auth') : 'corex-email-attempt';

        return hash_hmac('sha256', $normalized, $key);
    }
}
