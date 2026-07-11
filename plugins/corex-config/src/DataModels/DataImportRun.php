<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DataImportRun
{
    public const STATE_VALID = 'valid';
    public const STATE_INVALID = 'invalid';
    public const STATE_COMMITTING = 'committing';
    public const STATE_COMPLETED = 'completed';
    public const STATE_PARTIAL = 'partial';

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->id = (int) ($payload['id'] ?? 0);
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->jobId = (int) ($payload['job_id'] ?? 0);
        $this->sourceKey = (string) ($payload['source_key'] ?? '');
        $this->state = (string) ($payload['state'] ?? '');
        $this->fileName = (string) ($payload['file_name'] ?? 'import.csv');
        $this->header = array_values(array_map('strval', (array) ($payload['header'] ?? [])));
        $this->sourceRows = array_values((array) ($payload['rows'] ?? []));
        $this->mapping = is_array($payload['mapping'] ?? null) ? $payload['mapping'] : [];
        $this->unknownPolicy = (string) ($payload['unknown_policy'] ?? '');
        $this->unknownColumns = array_values(array_map('strval', (array) ($payload['unknown_columns'] ?? [])));
        $this->acceptedRows = array_values((array) ($payload['accepted_rows'] ?? []));
        $this->rejectedRows = array_values((array) ($payload['rejected_rows'] ?? []));
        $this->personalDataClasses = array_values(array_map('strval', (array) ($payload['personal_data_classes'] ?? [])));
        $this->inputHash = (string) ($payload['input_hash'] ?? '');
        $this->committedRows = (int) ($payload['committed_rows'] ?? 0);
        $this->failedRows = (int) ($payload['failed_rows'] ?? 0);
        $this->createdAt = new DateTimeImmutable((string) ($payload['created_at'] ?? 'now'));
        $this->validate();
    }

    public int $id;
    public int $actorId;
    public int $jobId;
    public string $sourceKey;
    public string $state;
    public string $fileName;
    /** @var list<string> */ public array $header;
    /** @var list<list<string>> */ public array $sourceRows;
    /** @var array<string,string> */ public array $mapping;
    public string $unknownPolicy;
    /** @var list<string> */ public array $unknownColumns;
    /** @var list<array<string,mixed>> */ public array $acceptedRows;
    /** @var list<array{line:int,reason:string,row:list<string>}> */ public array $rejectedRows;
    /** @var list<string> */ public array $personalDataClasses;
    public string $inputHash;
    public int $committedRows;
    public int $failedRows;
    public DateTimeImmutable $createdAt;

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        return new self($payload);
    }

    /** @param array<string,mixed> $plan */
    public static function planned(DataImportRequest $request, array $plan): self
    {
        $payload = [
            ...$request->toArray(),
            ...$plan,
            'state' => ($plan['accepted_rows'] ?? []) === [] ? self::STATE_INVALID : self::STATE_VALID,
            'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
        $payload['input_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return self::from($payload);
    }

    public function withId(int $id): self
    {
        return self::from([...$this->toArray(), 'id' => $id]);
    }

    public function withJob(int $jobId): self
    {
        return self::from([...$this->toArray(), 'job_id' => $jobId, 'state' => self::STATE_COMMITTING]);
    }

    public function withResult(int $succeeded, int $failed): self
    {
        return self::from([
            ...$this->toArray(),
            'state' => $failed === 0 ? self::STATE_COMPLETED : self::STATE_PARTIAL,
            'committed_rows' => $succeeded,
            'failed_rows' => $failed,
        ]);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'actor_id' => $this->actorId,
            'job_id' => $this->jobId,
            'source_key' => $this->sourceKey,
            'state' => $this->state,
            'file_name' => $this->fileName,
            'header' => $this->header,
            'rows' => $this->sourceRows,
            'mapping' => $this->mapping,
            'unknown_policy' => $this->unknownPolicy,
            'unknown_columns' => $this->unknownColumns,
            'accepted_rows' => $this->acceptedRows,
            'rejected_rows' => $this->rejectedRows,
            'personal_data_classes' => $this->personalDataClasses,
            'input_hash' => $this->inputHash,
            'committed_rows' => $this->committedRows,
            'failed_rows' => $this->failedRows,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->jobId < 0 || preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1) {
            throw new InvalidArgumentException('The data import identifiers are invalid.');
        }
        if (! in_array($this->state, [self::STATE_VALID, self::STATE_INVALID, self::STATE_COMMITTING, self::STATE_COMPLETED, self::STATE_PARTIAL], true)) {
            throw new InvalidArgumentException('The data import state is invalid.');
        }
        if (preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('The data import checksum is invalid.');
        }
    }
}
