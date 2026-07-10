<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Durable export history record; it stores scope, columns, actor, count, and job linkage.
 */
final readonly class SubmissionExportRun
{
    public int $id;
    public int $actorId;
    public int $jobId;
    public string $scope;
    /** @var list<int> */
    public array $selectedIds;
    /** @var list<string> */
    public array $columns;
    /** @var array<string,mixed> */
    public array $query;
    public bool $includeTest;
    public string $format;
    public int $recordCount;
    public string $inputHash;
    public DateTimeImmutable $createdAt;

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->id = (int) ($payload['id'] ?? 0);
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->jobId = (int) ($payload['job_id'] ?? 0);
        $this->scope = (string) ($payload['scope'] ?? '');
        $this->selectedIds = array_values(array_map('intval', (array) ($payload['selected_ids'] ?? [])));
        $this->columns = array_values(array_map('strval', (array) ($payload['columns'] ?? [])));
        $this->query = is_array($payload['query'] ?? null) ? $payload['query'] : [];
        $this->includeTest = (bool) ($payload['include_test'] ?? false);
        $this->format = (string) ($payload['format'] ?? 'csv');
        $this->recordCount = (int) ($payload['record_count'] ?? 0);
        $this->inputHash = (string) ($payload['input_hash'] ?? '');
        $this->createdAt = new DateTimeImmutable((string) ($payload['created_at'] ?? 'now'));
        $this->validate();
    }

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self
    {
        return new self($payload);
    }

    public static function queued(int $actorId, SubmissionExportRequest $request, int $recordCount): self
    {
        $payload = $request->toArray();
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        return self::from([
            ...$payload,
            'actor_id' => $actorId,
            'record_count' => $recordCount,
            'input_hash' => hash('sha256', $actorId . '|' . $encoded),
            'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
        ]);
    }

    public function withId(int $id): self
    {
        return self::from([...$this->toArray(), 'id' => $id]);
    }

    public function withJob(int $jobId): self
    {
        return self::from([...$this->toArray(), 'job_id' => $jobId]);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'actor_id' => $this->actorId,
            'job_id' => $this->jobId,
            'scope' => $this->scope,
            'selected_ids' => $this->selectedIds,
            'columns' => $this->columns,
            'query' => $this->query,
            'include_test' => $this->includeTest,
            'format' => $this->format,
            'record_count' => $this->recordCount,
            'input_hash' => $this->inputHash,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->jobId < 0 || $this->recordCount < 0) {
            throw new InvalidArgumentException('The submission export identifiers are invalid.');
        }
        if (! in_array($this->scope, SubmissionExportRequest::SCOPES, true)) {
            throw new InvalidArgumentException('The submission export scope is invalid.');
        }
        if (preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('The submission export input hash is invalid.');
        }
    }
}
