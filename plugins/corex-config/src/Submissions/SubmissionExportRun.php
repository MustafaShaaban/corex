<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportWriters;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Durable export history record; it stores scope, columns, actor, count, and job linkage, and
 * what became of the file: how large it was, and whether it expired or somebody deleted it.
 */
final readonly class SubmissionExportRun
{
    /** The file was removed because it passed its retention. */
    public const REMOVED_EXPIRED = 'expired';

    /** The file was removed because a person deleted it. */
    public const REMOVED_DELETED = 'deleted';

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
    public string $separator;
    public int $recordCount;
    public string $inputHash;
    public DateTimeImmutable $createdAt;
    /** In bytes; 0 until the file is written, and for an export made before sizes were kept. */
    public int $fileSize;
    /** `''` while the file is kept, or one of the `REMOVED_*` reasons. */
    public string $removedReason;
    /** The person who deleted the file; 0 when it expired or is still kept. */
    public int $removedBy;
    public ?DateTimeImmutable $removedAt;

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
        $this->format = (string) ($payload['format'] ?? ExportWriters::CSV);
        $this->separator = (string) ($payload['separator'] ?? ExportWriters::DEFAULT_SEPARATOR);
        $this->recordCount = (int) ($payload['record_count'] ?? 0);
        $this->inputHash = (string) ($payload['input_hash'] ?? '');
        $this->createdAt = new DateTimeImmutable((string) ($payload['created_at'] ?? 'now'));
        $this->fileSize = (int) ($payload['file_size'] ?? 0);
        $this->removedReason = (string) ($payload['removed_reason'] ?? '');
        $this->removedBy = (int) ($payload['removed_by'] ?? 0);
        $this->removedAt = ((string) ($payload['removed_at'] ?? '')) === ''
            ? null
            : new DateTimeImmutable((string) $payload['removed_at']);
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
            // Salted, so the same request made twice is two exports. The job that writes an export
            // finds it by this hash, and an unsalted one sent both jobs to the newer run.
            'input_hash' => hash('sha256', $actorId . '|' . $encoded . '|' . bin2hex(random_bytes(8))),
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

    public function withFileSize(int $bytes): self
    {
        return self::from([...$this->toArray(), 'file_size' => $bytes]);
    }

    /**
     * The run once its file is gone. It keeps the size the file had.
     *
     * @param string $reason  One of the `REMOVED_*` reasons.
     * @param int    $actorId The person who deleted it; 0 when it expired.
     */
    public function withoutFile(string $reason, int $actorId, DateTimeImmutable $at): self
    {
        return self::from([
            ...$this->toArray(),
            'removed_reason' => $reason,
            'removed_by' => $actorId,
            'removed_at' => $at->format(DATE_ATOM),
        ]);
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
            'separator' => $this->separator,
            'record_count' => $this->recordCount,
            'input_hash' => $this->inputHash,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'file_size' => $this->fileSize,
            'removed_reason' => $this->removedReason,
            'removed_by' => $this->removedBy,
            'removed_at' => $this->removedAt?->format(DATE_ATOM) ?? '',
        ];
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->jobId < 0 || $this->recordCount < 0) {
            throw new InvalidArgumentException('The submission export identifiers are invalid.');
        }
        if ($this->fileSize < 0 || $this->removedBy < 0) {
            throw new InvalidArgumentException('The submission export file record is invalid.');
        }
        if (! in_array($this->removedReason, ['', self::REMOVED_EXPIRED, self::REMOVED_DELETED], true)) {
            throw new InvalidArgumentException('The reason the submission export file was removed is invalid.');
        }
        if (! in_array($this->scope, SubmissionExportRequest::SCOPES, true)) {
            throw new InvalidArgumentException('The submission export scope is invalid.');
        }
        if (preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('The submission export input hash is invalid.');
        }
    }
}
