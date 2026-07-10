<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Config\Data\DataSourceService;
use Corex\Data\DataSourceCapabilities;
use DomainException;

/** Preview, snapshot, queue, rollback, and history boundary for source migrations. */
final readonly class MigrationService
{
    public function __construct(
        private DataSourceService $sources,
        private MigrationPreviewStore $previews,
        private MigrationRunStore $runs,
        private MigrationJobQueue $jobs,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function catalog(int $actorId, string $sourceKey): array
    {
        return array_map(
            static fn (MigrationDefinition $definition): array => $definition->toArray(),
            $this->provider($actorId, $sourceKey, DataSourceCapabilities::MIGRATIONS)->definitions(),
        );
    }

    public function previewApply(int $actorId, string $sourceKey, string $definitionKey): MigrationPreview
    {
        $definition = $this->definition(
            $this->provider($actorId, $sourceKey, DataSourceCapabilities::MIGRATIONS),
            $definitionKey,
        );

        return $this->previews->issue($actorId, MigrationRun::ACTION_APPLY, $sourceKey, $definition);
    }

    public function previewRollback(int $actorId, int $runId): MigrationPreview
    {
        $run = $this->runs->find($runId);
        if ($run === null || $run->actorId !== $actorId) {
            throw new DomainException('The migration run is unavailable for rollback.');
        }
        try {
            $provider = $this->provider($actorId, $run->sourceKey, DataSourceCapabilities::ROLLBACK);
        } catch (DomainException) {
            throw new DomainException('The data source does not support rollback.');
        }
        if ($run->state !== MigrationRun::STATE_APPLIED || ! $run->definition->rollbackSupported) {
            throw new DomainException('The migration run cannot be rolled back.');
        }
        $definition = $this->definition($provider, $run->definition->key);

        return $this->previews->issue($actorId, MigrationRun::ACTION_ROLLBACK, $run->sourceKey, $definition, $run->id);
    }

    public function queue(int $actorId, string $token, string $expectedAction = '', int $expectedRunId = 0): MigrationRun
    {
        $preview = $this->previews->consume($token, $actorId);
        if ($preview === null) {
            throw new DomainException('The migration preview expired or was already used.');
        }
        if (($expectedAction !== '' && $preview->action !== $expectedAction)
            || ($expectedRunId > 0 && $preview->runId !== $expectedRunId)) {
            throw new DomainException('The migration preview does not match the requested operation.');
        }
        $operation = $preview->action === MigrationRun::ACTION_ROLLBACK
            ? DataSourceCapabilities::ROLLBACK : DataSourceCapabilities::MIGRATIONS;
        $provider = $this->provider($actorId, $preview->sourceKey, $operation);
        $definition = $this->definition($provider, $preview->definition->key);
        if (! hash_equals($preview->definition->hash(), $definition->hash())) {
            throw new DomainException('The migration plan changed after preview.');
        }
        $snapshot = $preview->action === MigrationRun::ACTION_ROLLBACK
            ? $this->rollbackSnapshot($preview)
            : $provider->snapshot($definition);
        $run = $this->runs->create(MigrationRun::queued($preview, $snapshot));

        return $this->runs->attachJob($run->id, $this->jobs->enqueue($run));
    }

    /** @return list<MigrationRun> */
    public function history(int $actorId, bool $manageAll = false, int $limit = 50): array
    {
        return $this->runs->history($actorId, $manageAll, min(100, max(1, $limit)));
    }

    private function provider(int $actorId, string $sourceKey, string $operation): MigrationProvider
    {
        $source = $this->sources->authorize($actorId, $sourceKey, $operation);
        if (! $source instanceof MigrationAwareDataSource) {
            throw new DomainException('The data source does not provide a migration adapter.');
        }

        return $source->migrationProvider();
    }

    private function definition(MigrationProvider $provider, string $key): MigrationDefinition
    {
        foreach ($provider->definitions() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        throw new DomainException('The migration definition is unavailable.');
    }

    private function rollbackSnapshot(MigrationPreview $preview): string
    {
        $run = $this->runs->find($preview->runId);
        if ($run === null || $run->state !== MigrationRun::STATE_APPLIED) {
            throw new DomainException('The migration rollback source run is unavailable.');
        }

        return $run->snapshotId;
    }
}
