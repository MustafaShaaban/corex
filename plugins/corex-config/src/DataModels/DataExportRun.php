<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DataExportRun
{
    public const STATE_QUEUED = 'queued';
    public const STATE_COMPLETED = 'completed';

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->id = (int) ($payload['id'] ?? 0);
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->jobId = (int) ($payload['job_id'] ?? 0);
        $this->sourceKey = (string) ($payload['source_key'] ?? '');
        $this->scope = (string) ($payload['scope'] ?? '');
        $this->selectedIds = array_values(array_map('intval', (array) ($payload['selected_ids'] ?? [])));
        $this->query = is_array($payload['query'] ?? null) ? $payload['query'] : [];
        $this->columns = array_values(array_map('strval', (array) ($payload['columns'] ?? [])));
        $this->format = (string) ($payload['format'] ?? '');
        $this->personalDataClasses = array_values(array_map('strval', (array) ($payload['personal_data_classes'] ?? [])));
        $this->recordCount = (int) ($payload['record_count'] ?? 0);
        $this->exportedRows = (int) ($payload['exported_rows'] ?? 0);
        $this->state = (string) ($payload['state'] ?? '');
        $this->inputHash = (string) ($payload['input_hash'] ?? '');
        $this->createdAt = new DateTimeImmutable((string) ($payload['created_at'] ?? 'now'));
        $this->validate();
    }

    public int $id;
    public int $actorId;
    public int $jobId;
    public string $sourceKey;
    public string $scope;
    /** @var list<int> */ public array $selectedIds;
    /** @var array<string,mixed> */ public array $query;
    /** @var list<string> */ public array $columns;
    public string $format;
    /** @var list<string> */ public array $personalDataClasses;
    public int $recordCount;
    public int $exportedRows;
    public string $state;
    public string $inputHash;
    public DateTimeImmutable $createdAt;

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self { return new self($payload); }

    /** @param list<string> $personalDataClasses */
    public static function queued(DataExportRequest $request, int $recordCount, array $personalDataClasses): self
    {
        $payload = [
            ...$request->toArray(), 'record_count' => $recordCount,
            'personal_data_classes' => $personalDataClasses,
            'state' => self::STATE_QUEUED,
            'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ];
        $payload['input_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return self::from($payload);
    }

    public function withId(int $id): self { return self::from([...$this->toArray(), 'id' => $id]); }
    public function withJob(int $jobId): self { return self::from([...$this->toArray(), 'job_id' => $jobId]); }
    public function completed(int $rows): self { return self::from([...$this->toArray(), 'state' => self::STATE_COMPLETED, 'exported_rows' => $rows]); }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'actor_id' => $this->actorId, 'job_id' => $this->jobId,
            'source_key' => $this->sourceKey, 'scope' => $this->scope, 'selected_ids' => $this->selectedIds,
            'query' => $this->query, 'columns' => $this->columns, 'format' => $this->format,
            'personal_data_classes' => $this->personalDataClasses, 'record_count' => $this->recordCount,
            'exported_rows' => $this->exportedRows, 'state' => $this->state, 'input_hash' => $this->inputHash,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->jobId < 0 || $this->recordCount < 0 || $this->exportedRows < 0) {
            throw new InvalidArgumentException('Data export identifiers or counts are invalid.');
        }
        if (! in_array($this->state, [self::STATE_QUEUED, self::STATE_COMPLETED], true)
            || preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('Data export state or checksum is invalid.');
        }
    }
}
