<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Data\DataSourceService;
use Corex\Data\DataSourceCapabilities;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use DateTimeImmutable;
use DomainException;
use Throwable;

/** Executes one already-snapshotted migration or rollback as a bounded job. */
final readonly class MigrationJobHandler implements JobHandler
{
    public const KIND = 'data.migration';

    public function __construct(
        private DataSourceService $sources,
        private MigrationRunStore $runs,
        private ActivityService $activity,
    ) {
    }

    public function kind(): string { return self::KIND; }

    public function handle(BoundedJob $job, int $batchSize): BoundedJob
    {
        $run = $this->runs->findByHash($job->inputHash);
        if ($run === null || $run->state !== MigrationRun::STATE_QUEUED) {
            throw new DomainException('The queued migration is unavailable.');
        }
        $operation = $run->action === MigrationRun::ACTION_ROLLBACK
            ? DataSourceCapabilities::ROLLBACK : DataSourceCapabilities::MIGRATIONS;
        $source = $this->sources->authorize($job->actorId, $run->sourceKey, $operation);
        if (! $source instanceof MigrationAwareDataSource) {
            throw new DomainException('The migration adapter is unavailable.');
        }

        try {
            $result = $source->migrationProvider()->execute(
                $run->definition,
                $run->snapshotId,
                $run->action === MigrationRun::ACTION_ROLLBACK,
            );
        } catch (Throwable $error) {
            return $this->failed($job, $run, $error->getMessage());
        }
        if (! $result->succeeded()) {
            return $this->failed($job, $run, $result->message);
        }

        $state = $run->action === MigrationRun::ACTION_ROLLBACK
            ? MigrationRun::STATE_ROLLED_BACK : MigrationRun::STATE_APPLIED;
        $this->runs->finish($run->id, $state, $result->message);
        $this->audit($run, $state, ActivityEvent::OUTCOME_SUCCESS);
        $now = new DateTimeImmutable('now');

        return $job->advance('1', 1, 1, 0, null, $now)->complete('migration-run:' . $run->id, $now);
    }

    private function failed(BoundedJob $job, MigrationRun $run, string $message): BoundedJob
    {
        $safeMessage = trim($message) !== '' ? mb_substr(strip_tags($message), 0, 500) : 'Migration failed.';
        $this->runs->finish($run->id, MigrationRun::STATE_FAILED, $safeMessage);
        $this->audit($run, MigrationRun::STATE_FAILED, ActivityEvent::OUTCOME_FAILURE);

        return $job->fail($safeMessage, new DateTimeImmutable('now'));
    }

    private function audit(MigrationRun $run, string $state, string $outcome): void
    {
        $kind = match ($state) {
            MigrationRun::STATE_APPLIED => 'data.migration.applied',
            MigrationRun::STATE_ROLLED_BACK => 'data.migration.rolled_back',
            default => 'data.migration.failed',
        };
        $now = new DateTimeImmutable('now');
        $this->activity->record(
            actorId: $run->actorId,
            actorKind: ActivityEvent::ACTOR_SYSTEM,
            actorLabel: 'CoreX Jobs',
            area: ActivityEvent::AREA_DATA_MODELS,
            kind: $kind,
            targetType: 'migration_run',
            targetId: (string) $run->id,
            targetLabel: $run->definition->key,
            outcome: $outcome,
            summary: ['key' => $kind, 'args' => ['migration' => $run->definition->key]],
            context: [
                'source' => $run->sourceKey, 'version' => $run->definition->version,
                'snapshot_id' => $run->snapshotId, 'transactional' => $run->definition->transactional,
            ],
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: $now->modify('+1 year'),
            occurredAt: $now,
        );
    }
}
