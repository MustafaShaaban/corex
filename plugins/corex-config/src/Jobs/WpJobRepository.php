<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Jobs;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class WpJobRepository implements JobRepository
{
    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function create(BoundedJob $job): BoundedJob
    {
        global $wpdb;

        $previousSuppression = $wpdb->suppress_errors(true);
        $inserted            = $wpdb->insert($this->table(), $this->row($job));
        $wpdb->suppress_errors($previousSuppression);

        if ($inserted === false) {
            $active = $this->findActive($job->kind, $job->inputHash);
            if ($active !== null) {
                return $active;
            }

            throw new RuntimeException('CoreX could not create the bounded job.');
        }

        return $job->withId((int) $wpdb->insert_id);
    }

    public function find(int $id): ?BoundedJob
    {
        global $wpdb;

        if ($id < 1) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE id = %d',
            $id,
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findActive(string $kind, string $inputHash): ?BoundedJob
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->table()
            . ' WHERE kind = %s AND input_hash = %s AND state IN (%s, %s, %s) ORDER BY id DESC LIMIT 1',
            $kind,
            $inputHash,
            BoundedJob::STATE_QUEUED,
            BoundedJob::STATE_RUNNING,
            BoundedJob::STATE_PAUSED,
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function save(BoundedJob $job): void
    {
        global $wpdb;

        if ($job->id < 1 || $wpdb->update($this->table(), $this->row($job), ['id' => $job->id]) === false) {
            throw new RuntimeException('CoreX could not update the bounded job.');
        }
    }

    /** @return array<string,mixed> */
    private function row(BoundedJob $job): array
    {
        return [
            'kind'            => $job->kind,
            'actor_id'        => $job->actorId,
            'state'           => $job->state,
            'cursor_value'    => $job->cursor,
            'total'           => $job->total,
            'processed'       => $job->processed,
            'succeeded'       => $job->succeeded,
            'failed'          => $job->failed,
            'input_hash'      => $job->inputHash,
            'active_key'      => $job->terminal() ? null : hash('sha256', $job->kind . '|' . $job->inputHash),
            'result_artifact' => $job->resultArtifact,
            'error_summary'   => $job->errorSummary,
            'attempts'        => $job->attempts,
            'next_run_at'     => $this->nullableDate($job->nextRunAt),
            'created_at'      => $this->date($job->createdAt),
            'updated_at'      => $this->date($job->updatedAt),
            'finished_at'     => $this->nullableDate($job->finishedAt),
        ];
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): BoundedJob
    {
        return new BoundedJob(
            id: (int) $row['id'],
            kind: (string) $row['kind'],
            actorId: (int) $row['actor_id'],
            state: (string) $row['state'],
            cursor: (string) $row['cursor_value'],
            total: (int) $row['total'],
            processed: (int) $row['processed'],
            succeeded: (int) $row['succeeded'],
            failed: (int) $row['failed'],
            inputHash: (string) $row['input_hash'],
            resultArtifact: $row['result_artifact'] !== null ? (string) $row['result_artifact'] : null,
            errorSummary: $row['error_summary'] !== null ? (string) $row['error_summary'] : null,
            attempts: (int) $row['attempts'],
            nextRunAt: $this->storedNullableDate($row['next_run_at']),
            createdAt: $this->storedDate((string) $row['created_at']),
            updatedAt: $this->storedDate((string) $row['updated_at']),
            finishedAt: $this->storedNullableDate($row['finished_at']),
        );
    }

    private function table(): string
    {
        return $this->migrator->fullName(JobTable::NAME);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function nullableDate(?DateTimeImmutable $date): ?string
    {
        return $date?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function storedDate(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    private function storedNullableDate(mixed $date): ?DateTimeImmutable
    {
        return is_string($date) && $date !== '' ? $this->storedDate($date) : null;
    }
}
