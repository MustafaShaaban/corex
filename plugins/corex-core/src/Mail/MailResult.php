<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Secret-free outcome of one mail attempt, safe to return to callers and admin UI.
 */
final class MailResult
{
    public const STATE_ACCEPTED = 'accepted';
    public const STATE_CAPTURED = 'captured';
    public const STATE_QUEUED   = 'queued';
    public const STATE_SENDING  = 'sending';
    public const STATE_SENT     = 'sent';
    public const STATE_FAILED   = 'failed';
    public const STATE_REJECTED = 'rejected';
    public const STATE_BOUNCED  = 'bounced';
    public const STATE_OPENED   = 'opened';

    private const STATES = [
        self::STATE_ACCEPTED,
        self::STATE_CAPTURED,
        self::STATE_QUEUED,
        self::STATE_SENDING,
        self::STATE_SENT,
        self::STATE_FAILED,
        self::STATE_REJECTED,
        self::STATE_BOUNCED,
        self::STATE_OPENED,
    ];

    public function __construct(
        public readonly string $attemptId,
        public readonly string $requestId,
        public readonly string $state,
        public readonly string $provider,
        public readonly string $message,
        public readonly DateTimeImmutable $occurredAt,
        public readonly bool $retryable,
        public readonly ?int $logId = null,
        public readonly ?string $parentAttemptId = null,
    ) {
        foreach ([$this->attemptId, $this->requestId, $this->parentAttemptId] as $id) {
            if ($id !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id) !== 1) {
                throw new InvalidArgumentException('Mail result IDs must be version 4 UUIDs.');
            }
        }

        if (! in_array($this->state, self::STATES, true)) {
            throw new InvalidArgumentException('Mail result state is invalid.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->provider) !== 1 || $this->message === '') {
            throw new InvalidArgumentException('Mail result provider or message is invalid.');
        }

        if ($this->logId !== null && $this->logId < 1) {
            throw new InvalidArgumentException('Mail result log ID must be positive.');
        }
    }

    public function successful(): bool
    {
        return in_array($this->state, [
            self::STATE_ACCEPTED,
            self::STATE_CAPTURED,
            self::STATE_QUEUED,
            self::STATE_SENT,
            self::STATE_OPENED,
        ], true);
    }

    public function terminal(): bool
    {
        return in_array($this->state, [
            self::STATE_CAPTURED,
            self::STATE_SENT,
            self::STATE_FAILED,
            self::STATE_REJECTED,
            self::STATE_BOUNCED,
            self::STATE_OPENED,
        ], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'attempt_id'       => $this->attemptId,
            'request_id'       => $this->requestId,
            'parent_attempt_id' => $this->parentAttemptId,
            'state'            => $this->state,
            'provider'         => $this->provider,
            'message'          => $this->message,
            'occurred_at'      => $this->occurredAt->format(DATE_ATOM),
            'retryable'        => $this->retryable,
            'log_id'           => $this->logId,
        ];
    }
}
