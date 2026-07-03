<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Immutable state of one bounded, resumable background operation.
 */
final class BoundedJob
{
    public const STATE_QUEUED    = 'queued';
    public const STATE_RUNNING   = 'running';
    public const STATE_PAUSED    = 'paused';
    public const STATE_COMPLETED = 'completed';
    public const STATE_PARTIAL   = 'partial';
    public const STATE_FAILED    = 'failed';
    public const STATE_CANCELLED = 'cancelled';

    private const STATES = [
        self::STATE_QUEUED,
        self::STATE_RUNNING,
        self::STATE_PAUSED,
        self::STATE_COMPLETED,
        self::STATE_PARTIAL,
        self::STATE_FAILED,
        self::STATE_CANCELLED,
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $kind,
        public readonly int $actorId,
        public readonly string $state,
        public readonly string $cursor,
        public readonly int $total,
        public readonly int $processed,
        public readonly int $succeeded,
        public readonly int $failed,
        public readonly string $inputHash,
        public readonly ?string $resultArtifact,
        public readonly ?string $errorSummary,
        public readonly int $attempts,
        public readonly ?DateTimeImmutable $nextRunAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly ?DateTimeImmutable $finishedAt,
    ) {
        $this->validate();
    }

    public static function queued(
        string $kind,
        int $actorId,
        int $total,
        string $inputHash,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: 0,
            kind: $kind,
            actorId: $actorId,
            state: self::STATE_QUEUED,
            cursor: '',
            total: $total,
            processed: 0,
            succeeded: 0,
            failed: 0,
            inputHash: $inputHash,
            resultArtifact: null,
            errorSummary: null,
            attempts: 0,
            nextRunAt: $createdAt,
            createdAt: $createdAt,
            updatedAt: $createdAt,
            finishedAt: null,
        );
    }

    public function withId(int $id): self
    {
        return $this->copy(['id' => $id]);
    }

    public function start(DateTimeImmutable $now): self
    {
        $this->assertState([self::STATE_QUEUED, self::STATE_PAUSED]);

        return $this->copy([
            'state'       => self::STATE_RUNNING,
            'attempts'    => $this->attempts + 1,
            'nextRunAt'   => null,
            'updatedAt'   => $now,
        ]);
    }

    public function advance(
        string $cursor,
        int $processed,
        int $succeeded,
        int $failed,
        ?DateTimeImmutable $nextRunAt,
        DateTimeImmutable $updatedAt,
    ): self {
        $this->assertState([self::STATE_RUNNING]);

        return $this->copy([
            'cursor'     => $cursor,
            'processed'  => $processed,
            'succeeded'  => $succeeded,
            'failed'     => $failed,
            'nextRunAt'  => $nextRunAt,
            'updatedAt'  => $updatedAt,
        ]);
    }

    public function complete(?string $resultArtifact, DateTimeImmutable $now): self
    {
        $this->assertState([self::STATE_RUNNING]);

        if ($this->processed !== $this->total) {
            throw new DomainException('A bounded job cannot complete before every item is processed.');
        }

        return $this->copy([
            'state'          => self::STATE_COMPLETED,
            'resultArtifact' => $resultArtifact,
            'nextRunAt'      => null,
            'updatedAt'      => $now,
            'finishedAt'     => $now,
        ]);
    }

    public function fail(string $errorSummary, DateTimeImmutable $now): self
    {
        $this->assertState([self::STATE_RUNNING]);

        return $this->copy([
            'state'        => self::STATE_FAILED,
            'errorSummary' => $errorSummary,
            'nextRunAt'    => null,
            'updatedAt'    => $now,
            'finishedAt'   => $now,
        ]);
    }

    public function cancel(DateTimeImmutable $now): self
    {
        if ($this->terminal()) {
            throw new DomainException('A terminal bounded job cannot be cancelled.');
        }

        return $this->copy([
            'state'      => self::STATE_CANCELLED,
            'nextRunAt'  => null,
            'updatedAt'  => $now,
            'finishedAt' => $now,
        ]);
    }

    public function retry(DateTimeImmutable $now): self
    {
        $this->assertState([self::STATE_FAILED, self::STATE_PARTIAL]);

        return $this->copy([
            'state'        => self::STATE_QUEUED,
            'errorSummary' => null,
            'nextRunAt'    => $now,
            'updatedAt'    => $now,
            'finishedAt'   => null,
        ]);
    }

    public function terminal(): bool
    {
        return in_array($this->state, [
            self::STATE_COMPLETED,
            self::STATE_PARTIAL,
            self::STATE_FAILED,
            self::STATE_CANCELLED,
        ], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'kind'            => $this->kind,
            'actor_id'        => $this->actorId,
            'state'           => $this->state,
            'cursor'          => $this->cursor,
            'total'           => $this->total,
            'processed'       => $this->processed,
            'succeeded'       => $this->succeeded,
            'failed'          => $this->failed,
            'input_hash'      => $this->inputHash,
            'result_artifact' => $this->resultArtifact,
            'error_summary'   => $this->errorSummary,
            'attempts'        => $this->attempts,
            'next_run_at'     => $this->nextRunAt?->format(DATE_ATOM),
            'created_at'      => $this->createdAt->format(DATE_ATOM),
            'updated_at'      => $this->updatedAt->format(DATE_ATOM),
            'finished_at'     => $this->finishedAt?->format(DATE_ATOM),
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->total < 0) {
            throw new InvalidArgumentException('Bounded job identifiers and totals are invalid.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]*$/', $this->kind) !== 1) {
            throw new InvalidArgumentException('Bounded job kind is invalid.');
        }

        if (preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('Bounded job input hash must be SHA-256.');
        }

        if (! in_array($this->state, self::STATES, true)) {
            throw new InvalidArgumentException('Bounded job state is invalid.');
        }

        if ($this->processed < 0 || $this->succeeded < 0 || $this->failed < 0
            || $this->processed > $this->total || ($this->succeeded + $this->failed) > $this->processed) {
            throw new InvalidArgumentException('Bounded job counters are inconsistent.');
        }

        if ($this->updatedAt < $this->createdAt || ($this->finishedAt !== null && $this->finishedAt < $this->createdAt)) {
            throw new InvalidArgumentException('Bounded job timestamps are inconsistent.');
        }
    }

    /** @param list<string> $states */
    private function assertState(array $states): void
    {
        if (! in_array($this->state, $states, true)) {
            throw new DomainException(sprintf('Bounded job cannot transition from %s.', $this->state));
        }
    }

    /** @param array<string,mixed> $changes */
    private function copy(array $changes): self
    {
        return new self(
            id: $changes['id'] ?? $this->id,
            kind: $this->kind,
            actorId: $this->actorId,
            state: $changes['state'] ?? $this->state,
            cursor: $changes['cursor'] ?? $this->cursor,
            total: $this->total,
            processed: $changes['processed'] ?? $this->processed,
            succeeded: $changes['succeeded'] ?? $this->succeeded,
            failed: $changes['failed'] ?? $this->failed,
            inputHash: $this->inputHash,
            resultArtifact: array_key_exists('resultArtifact', $changes)
                ? $changes['resultArtifact']
                : $this->resultArtifact,
            errorSummary: array_key_exists('errorSummary', $changes)
                ? $changes['errorSummary']
                : $this->errorSummary,
            attempts: $changes['attempts'] ?? $this->attempts,
            nextRunAt: array_key_exists('nextRunAt', $changes) ? $changes['nextRunAt'] : $this->nextRunAt,
            createdAt: $this->createdAt,
            updatedAt: $changes['updatedAt'] ?? $this->updatedAt,
            finishedAt: array_key_exists('finishedAt', $changes) ? $changes['finishedAt'] : $this->finishedAt,
        );
    }
}
