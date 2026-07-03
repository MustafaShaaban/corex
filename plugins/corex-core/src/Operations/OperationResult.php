<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Operations;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable outcome returned by every CoreX mutation boundary.
 */
final class OperationResult
{
    public const STATE_ACCEPTED  = 'accepted';
    public const STATE_COMPLETED = 'completed';
    public const STATE_PARTIAL   = 'partial';
    public const STATE_FAILED    = 'failed';
    public const STATE_CANCELLED = 'cancelled';
    public const STATE_BLOCKED   = 'blocked';

    private const STATES = [
        self::STATE_ACCEPTED,
        self::STATE_COMPLETED,
        self::STATE_PARTIAL,
        self::STATE_FAILED,
        self::STATE_CANCELLED,
        self::STATE_BLOCKED,
    ];

    /**
     * @param list<array{code:string,message:string}> $errors
     * @param list<int|string>                        $affectedIds
     */
    public function __construct(
        public readonly string $operationId,
        public readonly string $state,
        public readonly string $message,
        public readonly array $errors,
        public readonly array $affectedIds,
        public readonly DateTimeImmutable $startedAt,
        public readonly ?DateTimeImmutable $finishedAt = null,
        public readonly ?int $auditEventId = null,
    ) {
        $this->validate();
    }

    public function succeeded(): bool
    {
        return $this->state === self::STATE_COMPLETED;
    }

    public function terminal(): bool
    {
        return $this->state !== self::STATE_ACCEPTED;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'operation_id'   => $this->operationId,
            'state'          => $this->state,
            'message'        => $this->message,
            'errors'         => $this->errors,
            'affected_ids'   => $this->affectedIds,
            'started_at'     => $this->startedAt->format(DATE_ATOM),
            'finished_at'    => $this->finishedAt?->format(DATE_ATOM),
            'audit_event_id' => $this->auditEventId,
        ];
    }

    private function validate(): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $this->operationId) !== 1) {
            throw new InvalidArgumentException('Operation ID must be a valid version 4 UUID.');
        }

        if (! in_array($this->state, self::STATES, true)) {
            throw new InvalidArgumentException('Unsupported operation state.');
        }

        if ($this->message === '') {
            throw new InvalidArgumentException('Operation result message cannot be empty.');
        }

        if ($this->finishedAt !== null && $this->finishedAt < $this->startedAt) {
            throw new InvalidArgumentException('Operation finish time cannot precede its start time.');
        }

        if ($this->auditEventId !== null && $this->auditEventId < 1) {
            throw new InvalidArgumentException('Audit event ID must be positive.');
        }

        if (count($this->affectedIds) > 500) {
            throw new InvalidArgumentException('Operation result contains too many inline affected IDs.');
        }

        foreach ($this->errors as $error) {
            if (! is_array($error) || ($error['code'] ?? '') === '' || ($error['message'] ?? '') === '') {
                throw new InvalidArgumentException('Operation errors require safe code and message values.');
            }
        }
    }
}
