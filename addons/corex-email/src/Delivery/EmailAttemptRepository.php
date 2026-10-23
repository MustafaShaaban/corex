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

    /** How many of the newest attempts the launch checklist reads for a test email (spec 118). */
    public const TEST_LOOKBACK = 500;

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

        return array_map($this->attempt(...), $this->store->latest(self::TYPE, $limit));
    }

    /**
     * The newest test from Email Studio that the mail transport accepted since a moment, or null.
     *
     * Accepted means sent or opened: a captured test never left the site, and a failed one was
     * refused. The launch checklist's "Test email sent" asks this (spec 118, US-OVR-03). It reads
     * the newest attempts first and stops at the first older than `$since`; it looks no further
     * back than the newest {@see self::TEST_LOOKBACK}, so a site that sent more email than that
     * since its test is told to send another.
     */
    public function latestAcceptedTest(DateTimeImmutable $since): ?EmailAttempt
    {
        foreach ($this->store->latest(self::TYPE, self::TEST_LOOKBACK) as $record) {
            $attempt = $this->attempt($record);
            if ($attempt->occurredAt < $since) {
                return null;
            }

            if ($attempt->source === 'test' && in_array($attempt->state, [EmailAttempt::STATE_SENT, EmailAttempt::STATE_OPENED], true)) {
                return $attempt;
            }
        }

        return null;
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

    /**
     * Whether an attempt went to this address.
     *
     * Asked of the repository because an attempt does not keep who it went to: it keeps the
     * address with its middle taken out, for a person to read, and a keyed digest of it, which
     * is what is compared here.
     */
    public function wasSentTo(EmailAttempt $attempt, string $recipient): bool
    {
        return $attempt->recipientHash !== null
            && hash_equals($attempt->recipientHash, $this->recipientHash($recipient));
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
