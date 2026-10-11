<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Config\Export\ExportWriters;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One export: who asked, which models and columns, in which format, how far it got, and what
 * became of its file.
 *
 * A run holds a part for each model (spec 109, slice 2). A run of one part is the export a
 * person makes from one model, with its scope: ticked rows, the filters in force, or everything.
 * A run of several takes every record of each. A run stored before runs had parts is read as a
 * run of one part.
 */
final readonly class DataExportRun
{
    public const STATE_QUEUED = 'queued';
    public const STATE_COMPLETED = 'completed';

    /** Why a run no longer holds its file. */
    public const REMOVED_EXPIRED = 'expired';
    public const REMOVED_DELETED = 'deleted';

    /** @param array<string,mixed> $payload */
    private function __construct(array $payload)
    {
        $this->id = (int) ($payload['id'] ?? 0);
        $this->actorId = (int) ($payload['actor_id'] ?? 0);
        $this->jobId = (int) ($payload['job_id'] ?? 0);
        $this->parts = self::partsOf($payload);
        $this->sourceKey = $this->parts[0]->sourceKey;
        $this->columns = $this->parts[0]->columns;
        $this->scope = (string) ($payload['scope'] ?? '');
        $this->selectedIds = array_values(array_map('intval', (array) ($payload['selected_ids'] ?? [])));
        $this->query = is_array($payload['query'] ?? null) ? $payload['query'] : [];
        $this->format = (string) ($payload['format'] ?? '');
        $this->separator = (string) (($payload['separator'] ?? '') ?: ExportWriters::DEFAULT_SEPARATOR);
        $this->personalDataClasses = self::classesOf($this->parts);
        $this->recordCount = self::countOf($this->parts);
        $this->exportedRows = (int) ($payload['exported_rows'] ?? 0);
        $this->state = (string) ($payload['state'] ?? '');
        $this->inputHash = (string) ($payload['input_hash'] ?? '');
        $this->createdAt = new DateTimeImmutable((string) ($payload['created_at'] ?? 'now'));
        $this->fileBytes = (int) ($payload['file_bytes'] ?? 0);
        $this->removedReason = (string) ($payload['removed_reason'] ?? '');
        $this->removedBy = (int) ($payload['removed_by'] ?? 0);
        $this->removedAt = empty($payload['removed_at']) ? null : new DateTimeImmutable((string) $payload['removed_at']);
        $this->validate();
    }

    public int $id;
    public int $actorId;
    public int $jobId;
    /** @var list<DataExportPart> One per model, in the order they were asked for. Never empty. */
    public array $parts;
    /** The first part's source: the one source of a run of one part. */
    public string $sourceKey;
    /** @var list<string> The first part's columns: the columns of a run of one part. */
    public array $columns;
    public string $scope;
    /** @var list<int> */ public array $selectedIds;
    /** @var array<string,mixed> */ public array $query;
    public string $format;
    public string $separator;
    /** @var list<string> The kinds of personal data across every part still in the run. */
    public array $personalDataClasses;
    /** How many records were counted across every part still in the run. */
    public int $recordCount;
    public int $exportedRows;
    public string $state;
    public string $inputHash;
    public DateTimeImmutable $createdAt;
    /** How large the file was when it was written; kept after the file is gone. */
    public int $fileBytes;
    /** '' while the run holds its file or has none yet; else one of the `REMOVED_*`. */
    public string $removedReason;
    /** Who deleted the file; 0 when it expired or is still there. */
    public int $removedBy;
    public ?DateTimeImmutable $removedAt;

    /** @param array<string,mixed> $payload */
    public static function from(array $payload): self { return new self($payload); }

    /** @param list<string> $personalDataClasses */
    public static function queued(DataExportRequest $request, int $recordCount, array $personalDataClasses): self
    {
        return self::salted([
            ...$request->toArray(), 'record_count' => $recordCount,
            'personal_data_classes' => $personalDataClasses,
        ]);
    }

    /** @param list<DataExportPart> $parts The models that hold records, each counted. */
    public static function queuedSeveral(DataExportSetRequest $request, array $parts): self
    {
        return self::salted([
            'actor_id' => $request->actorId, 'scope' => DataExportRequest::SCOPE_ALL,
            'format' => $request->format, 'separator' => $request->separator,
            'parts' => array_map(static fn (DataExportPart $part): array => $part->toArray(), $parts),
        ]);
    }

    public function withId(int $id): self { return $this->with(['id' => $id]); }
    public function withJob(int $jobId): self { return $this->with(['job_id' => $jobId]); }
    public function completed(int $rows): self { return $this->with(['state' => self::STATE_COMPLETED, 'exported_rows' => $rows]); }
    public function withFileOf(int $bytes): self { return $this->with(['file_bytes' => $bytes]); }

    public function withoutFile(string $reason, int $actorId, DateTimeImmutable $at): self
    {
        return $this->with(['removed_reason' => $reason, 'removed_by' => $actorId, 'removed_at' => $at->format(DATE_ATOM)]);
    }

    /** The run with one model marked as not read: it could not be, when its turn came. */
    public function leavingOut(string $sourceKey): self
    {
        return $this->with(['parts' => array_map(
            static fn (DataExportPart $part): array => ($part->sourceKey === $sourceKey ? $part->leftOut() : $part)->toArray(),
            $this->parts,
        )]);
    }

    public function isSeveral(): bool
    {
        return count($this->parts) > 1;
    }

    /** Whether the run covers this model, a model that was left out among them. */
    public function holds(string $sourceKey): bool
    {
        return in_array($sourceKey, $this->sourceKeys(), true);
    }

    /** @return list<string> */
    public function sourceKeys(): array
    {
        return array_map(static fn (DataExportPart $part): string => $part->sourceKey, $this->parts);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'actor_id' => $this->actorId, 'job_id' => $this->jobId,
            'parts' => array_map(static fn (DataExportPart $part): array => $part->toArray(), $this->parts),
            // The first part's, as a run of one part has always said them.
            'source_key' => $this->sourceKey, 'columns' => $this->columns,
            'scope' => $this->scope, 'selected_ids' => $this->selectedIds,
            'query' => $this->query, 'format' => $this->format,
            'separator' => $this->separator,
            'personal_data_classes' => $this->personalDataClasses, 'record_count' => $this->recordCount,
            'exported_rows' => $this->exportedRows, 'state' => $this->state, 'input_hash' => $this->inputHash,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'file_bytes' => $this->fileBytes, 'removed_reason' => $this->removedReason,
            'removed_by' => $this->removedBy, 'removed_at' => $this->removedAt?->format(DATE_ATOM),
        ];
    }

    /** @param array<string,mixed> $changes */
    private function with(array $changes): self
    {
        return self::from([...$this->toArray(), ...$changes]);
    }

    /** @param array<string,mixed> $payload */
    private static function salted(array $payload): self
    {
        $payload += ['state' => self::STATE_QUEUED, 'created_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM)];
        // Salted, so the same request made twice is two exports. The job that writes an export
        // finds it by this hash, and an unsalted one sent both jobs to one run.
        $payload['input_hash'] = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR) . '|' . bin2hex(random_bytes(8)));

        return self::from($payload);
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return list<DataExportPart>
     */
    private static function partsOf(array $payload): array
    {
        $stored = $payload['parts'] ?? null;
        if (is_array($stored) && $stored !== []) {
            return array_values(array_map(
                static fn (mixed $part): DataExportPart => DataExportPart::from((array) $part),
                $stored,
            ));
        }

        // A run from before runs had parts: one source, said at the run's top level.
        return [DataExportPart::from($payload)];
    }

    /** @param list<DataExportPart> $parts @return list<string> */
    private static function classesOf(array $parts): array
    {
        $classes = [];
        foreach (self::read($parts) as $part) {
            $classes = [...$classes, ...$part->personalDataClasses];
        }
        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    /** @param list<DataExportPart> $parts */
    private static function countOf(array $parts): int
    {
        return array_sum(array_map(static fn (DataExportPart $part): int => $part->recordCount, self::read($parts)));
    }

    /** @param list<DataExportPart> $parts @return list<DataExportPart> The parts that were not left out. */
    private static function read(array $parts): array
    {
        return array_values(array_filter($parts, static fn (DataExportPart $part): bool => ! $part->leftOut));
    }

    private function validate(): void
    {
        if ($this->id < 0 || $this->actorId < 1 || $this->jobId < 0 || $this->exportedRows < 0) {
            throw new InvalidArgumentException('Data export identifiers or counts are invalid.');
        }
        if (! in_array($this->state, [self::STATE_QUEUED, self::STATE_COMPLETED], true)
            || preg_match('/^[0-9a-f]{64}$/', $this->inputHash) !== 1) {
            throw new InvalidArgumentException('Data export state or checksum is invalid.');
        }
        if ($this->isSeveral() && $this->scope !== DataExportRequest::SCOPE_ALL) {
            throw new InvalidArgumentException('An export of several models takes every record of each.');
        }
        if (! in_array($this->removedReason, ['', self::REMOVED_EXPIRED, self::REMOVED_DELETED], true)) {
            throw new InvalidArgumentException('Why a data export lost its file is invalid.');
        }
    }
}
