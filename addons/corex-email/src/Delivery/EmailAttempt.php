<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Delivery;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable, provider-neutral record of a delivery event.
 */
final class EmailAttempt
{
    public const STATE_CAPTURED = 'captured';
    public const STATE_QUEUED   = 'queued';
    public const STATE_SENDING  = 'sending';
    public const STATE_SENT     = 'sent';
    public const STATE_FAILED   = 'failed';
    public const STATE_REJECTED = 'rejected';
    public const STATE_BOUNCED  = 'bounced';
    public const STATE_OPENED   = 'opened';

    private const STATES = [
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
        public readonly int $id,
        public readonly string $attemptId,
        public readonly string $requestId,
        public readonly ?string $parentAttemptId,
        public readonly string $recipient,
        public readonly string $subject,
        public readonly ?string $templateSlug,
        public readonly string $state,
        public readonly string $provider,
        public readonly ?string $providerEvent,
        public readonly bool $retryable,
        public readonly DateTimeImmutable $occurredAt,
        public readonly string $source = 'application',
        public readonly ?string $recipientHash = null,
        public readonly string $environment = 'production',
        public readonly ?int $templateId = null,
        public readonly ?int $templateVersion = null,
        public readonly ?int $routeId = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $errorCode = null,
    ) {
        if ($this->id < 0) {
            throw new InvalidArgumentException(__('Email attempt ID cannot be negative.', 'corex'));
        }

        foreach ([$this->attemptId, $this->requestId, $this->parentAttemptId] as $correlationId) {
            if ($correlationId !== null && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $correlationId) !== 1) {
                throw new InvalidArgumentException(__('Email attempt correlation ID is invalid.', 'corex'));
            }
        }

        if (filter_var($this->recipient, FILTER_VALIDATE_EMAIL) === false
            && preg_match('/^[^@]*\*[^@]*@[a-z0-9.-]+$/i', $this->recipient) !== 1
        ) {
            throw new InvalidArgumentException(__('Email attempt recipient is invalid.', 'corex'));
        }

        if ($this->recipientHash !== null && preg_match('/^[0-9a-f]{64}$/', $this->recipientHash) !== 1) {
            throw new InvalidArgumentException(__('Email attempt recipient hash is invalid.', 'corex'));
        }

        if (! in_array($this->state, self::STATES, true)) {
            throw new InvalidArgumentException(__('Email attempt state is invalid.', 'corex'));
        }

        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->provider) !== 1) {
            throw new InvalidArgumentException(__('Email attempt provider is invalid.', 'corex'));
        }

        if (! in_array($this->source, ['application', 'route', 'test', 'resend'], true)) {
            throw new InvalidArgumentException(__('Email attempt source is invalid.', 'corex'));
        }

        if (! in_array($this->environment, ['local', 'development', 'staging', 'production'], true)) {
            throw new InvalidArgumentException(__('Email attempt environment is invalid.', 'corex'));
        }

        if ($this->templateSlug !== null && preg_match('/^[a-z][a-z0-9-]*$/', $this->templateSlug) !== 1) {
            throw new InvalidArgumentException(__('Email attempt template slug is invalid.', 'corex'));
        }

        foreach ([$this->templateId, $this->templateVersion, $this->routeId] as $referenceId) {
            if ($referenceId !== null && $referenceId < 1) {
                throw new InvalidArgumentException(__('Email attempt template reference is invalid.', 'corex'));
            }
        }

        if (($this->providerMessageId !== null && (trim($this->providerMessageId) === '' || strlen($this->providerMessageId) > 191))
            || ($this->errorCode !== null && preg_match('/^[a-z][a-z0-9_.-]*$/', $this->errorCode) !== 1)
        ) {
            throw new InvalidArgumentException(__('Email attempt provider evidence is invalid.', 'corex'));
        }
    }

    public function withId(int $id): self
    {
        return new self(
            id: $id,
            attemptId: $this->attemptId,
            requestId: $this->requestId,
            parentAttemptId: $this->parentAttemptId,
            recipient: $this->recipient,
            subject: $this->subject,
            templateSlug: $this->templateSlug,
            state: $this->state,
            provider: $this->provider,
            providerEvent: $this->providerEvent,
            retryable: $this->retryable,
            occurredAt: $this->occurredAt,
            source: $this->source,
            recipientHash: $this->recipientHash,
            environment: $this->environment,
            templateId: $this->templateId,
            templateVersion: $this->templateVersion,
            routeId: $this->routeId,
            providerMessageId: $this->providerMessageId,
            errorCode: $this->errorCode,
        );
    }

    public function withRecipientEvidence(string $redactedRecipient, string $recipientHash): self
    {
        return new self(
            id: $this->id,
            attemptId: $this->attemptId,
            requestId: $this->requestId,
            parentAttemptId: $this->parentAttemptId,
            recipient: $redactedRecipient,
            subject: $this->subject,
            templateSlug: $this->templateSlug,
            state: $this->state,
            provider: $this->provider,
            providerEvent: $this->providerEvent,
            retryable: $this->retryable,
            occurredAt: $this->occurredAt,
            source: $this->source,
            recipientHash: $recipientHash,
            environment: $this->environment,
            templateId: $this->templateId,
            templateVersion: $this->templateVersion,
            routeId: $this->routeId,
            providerMessageId: $this->providerMessageId,
            errorCode: $this->errorCode,
        );
    }
}
