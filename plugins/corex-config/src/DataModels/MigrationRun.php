<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MigrationRun
{
    public const ACTION_APPLY = 'apply';
    public const ACTION_ROLLBACK = 'rollback';
    public const STATE_QUEUED = 'queued';
    public const STATE_APPLIED = 'applied';
    public const STATE_ROLLED_BACK = 'rolled_back';
    public const STATE_FAILED = 'failed';

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->id = (int) ($payload['id'] ?? 0);
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->jobId = (int) ($payload['job_id'] ?? 0);
        $this->parentRunId = (int) ($payload['parent_run_id'] ?? 0);
        $this->action = (string) ($payload['action'] ?? '');
        $this->sourceKey = (string) ($payload['source_key'] ?? '');
        $this->definition = MigrationDefinition::from((array) ($payload['definition'] ?? []));
        $this->snapshotId = (string) ($payload['snapshot_id'] ?? '');
        $this->state = (string) ($payload['state'] ?? '');
        $this->message = (string) ($payload['message'] ?? 'Queued.');
        $this->inputHash = (string) ($payload['input_hash'] ?? '');
        $this->createdAt = new DateTimeImmutable((string) ($payload['created_at'] ?? 'now'));
        $this->validate();
    }

    public int $id;
    public int $actorId;
    public int $jobId;
    public int $parentRunId;
    public string $action;
    public string $sourceKey;
    public MigrationDefinition $definition;
    public string $snapshotId;
    public string $state;
    public string $message;
    public string $inputHash;
    public DateTimeImmutable $createdAt;

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self { return new self($payload); }

    public static function queued(MigrationPreview $preview, string $snapshotId): self
    {
        $payload = [
            'actor_id' => $preview->actorId, 'parent_run_id' => $preview->runId,
            'action' => $preview->action, 'source_key' => $preview->sourceKey,
            'definition' => $preview->definition->toArray(), 'snapshot_id' => $snapshotId,
            'state' => self::STATE_QUEUED, 'message' => 'Queued.',
            'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
        $payload['input_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return self::from($payload);
    }

    public function withId(int $id): self { return self::from([...$this->toArray(), 'id' => $id]); }
    public function withJob(int $jobId): self { return self::from([...$this->toArray(), 'job_id' => $jobId]); }
    public function finished(string $state, string $message): self { return self::from([...$this->toArray(), 'state' => $state, 'message' => $message]); }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'actor_id' => $this->actorId, 'job_id' => $this->jobId,
            'parent_run_id' => $this->parentRunId, 'action' => $this->action, 'source_key' => $this->sourceKey,
            'definition' => $this->definition->toArray(), 'snapshot_id' => $this->snapshotId,
            'state' => $this->state, 'message' => $this->message, 'input_hash' => $this->inputHash,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->jobId < 0 || $this->parentRunId < 0 || $this->snapshotId === '') {
            throw new InvalidArgumentException('Migration run identifiers or snapshot are invalid.');
        }
        if (! in_array($this->action, [self::ACTION_APPLY, self::ACTION_ROLLBACK], true)
            || ! in_array($this->state, [self::STATE_QUEUED, self::STATE_APPLIED, self::STATE_ROLLED_BACK, self::STATE_FAILED], true)
            || preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('Migration run action, state, or checksum is invalid.');
        }
    }
}
