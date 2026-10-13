<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Corex\Multisite\Events\SiteMigrationFailed;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteScope;
use Throwable;

/**
 * Runs idempotent per-site schema work through SiteScope, preserving partial-create
 * retry semantics and returning failures that ProviderRepository would only log.
 * Prefix handling deliberately stays in Migrator::fullName(): global $wpdb is read
 * per call, so switch_to_blog() already selects the correct site prefix (FR-031,
 * FR-034).
 */
final class SiteMigrationRunner
{
    public function __construct(
        private readonly SchemaMigrator $migrator,
        private readonly SchemaRegistry $schemas,
        private readonly SchemaVersionStore $versions,
        private readonly SiteScope $scope,
        private readonly NetworkContext $network,
        private readonly EventDispatcher $events,
    ) {
    }

    public function runForSite(int $siteId): SiteMigrationResult
    {
        $started = microtime(true);

        return $this->scope->run(
            $siteId,
            fn (): SiteMigrationResult => $this->runCurrentSite($siteId, $started),
        );
    }

    /**
     * Whether a run would have anything to do: a registered component's stored version
     * differs from the one it declares. One read, and no write.
     *
     * It answers from what the request has read, which is enough to decide that a site
     * needs no run. A run that follows reads again (see runCurrentSite()).
     */
    public function isDueForSite(int $siteId): bool
    {
        return $this->scope->run($siteId, fn (): bool => $this->isDue($this->storedVersions()));
    }

    /**
     * Continues after a throwing site and returns that failure, unlike the provider
     * repository's boot isolation which can only log it (spec 100 FR-031).
     *
     * @param list<int> $siteIds
     */
    public function runForSites(array $siteIds): NetworkMigrationResult
    {
        $results = [];

        foreach ($siteIds as $siteId) {
            $started = microtime(true);

            try {
                $results[] = $this->runForSite($siteId);
            } catch (Throwable $e) {
                $results[] = new SiteMigrationResult(
                    $siteId,
                    SiteMigrationOutcome::Failed,
                    error: $e->getMessage(),
                    durationMs: $this->durationSince($started),
                );
            }
        }

        return new NetworkMigrationResult($results);
    }

    /**
     * Batched network sweep; resume with $result->nextOffset until $result->complete.
     */
    public function runForNetwork(int $batchSize = 50, int $offset = 0): NetworkMigrationResult
    {
        $batchSize = max(1, $batchSize);
        $siteIds = $this->network->siteIds($batchSize, $offset);
        $result = $this->runForSites($siteIds);

        return new NetworkMigrationResult(
            $result->results,
            $offset + count($siteIds),
            count($siteIds) < $batchSize,
        );
    }

    /** @return list<int> sites whose stored version differs from a registered component */
    public function pendingSiteIds(int $batchSize = 0, int $offset = 0): array
    {
        $pending = [];

        foreach ($this->network->siteIds($batchSize, $offset) as $siteId) {
            if ($this->isDueForSite($siteId)) {
                $pending[] = $siteId;
            }
        }

        return $pending;
    }

    /** @return array<string, string> The site in scope's stored version, by option name. */
    private function storedVersions(): array
    {
        return $this->versions->getMany($this->optionNames());
    }

    /** @return list<string> */
    private function optionNames(): array
    {
        return array_map(
            static fn (SchemaComponent $component): string => $component->optionName,
            $this->schemas->all(),
        );
    }

    /** @param array<string, string> $stored */
    private function isDue(array $stored): bool
    {
        foreach ($this->schemas->all() as $component) {
            if ($stored[$component->optionName] !== $component->version) {
                return true;
            }
        }

        return false;
    }

    private function durationSince(float $started): float
    {
        return (microtime(true) - $started) * 1000;
    }

    private function runCurrentSite(int $siteId, float $started): SiteMigrationResult
    {
        if (! is_file(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            return new SiteMigrationResult(
                $siteId,
                SiteMigrationOutcome::Skipped,
                error: 'WordPress upgrade.php is unavailable.',
                durationMs: $this->durationSince($started),
            );
        }

        // What is stored now, and not what this request read earlier: a caller that asked
        // isDueForSite() first may have been answered before another request finished the
        // same migration, and would run it and announce it a second time.
        $this->versions->forget($this->optionNames());
        $stored = $this->storedVersions();

        $componentsMigrated = [];
        $tablesCreated = [];
        $errors = [];

        foreach ($this->schemas->all() as $component) {
            $componentResult = $this->migrateComponent($siteId, $component, $stored[$component->optionName]);

            if ($componentResult['error'] !== null) {
                $errors[] = $componentResult['error'];
            } elseif ($componentResult['migrated']) {
                $componentsMigrated[] = $component->id;
                array_push($tablesCreated, ...$componentResult['tables']);
            }
        }

        return new SiteMigrationResult(
            $siteId,
            $this->outcome($componentsMigrated, $errors),
            $componentsMigrated,
            $tablesCreated,
            $errors === [] ? null : implode(' ', $errors),
            $this->durationSince($started),
        );
    }

    /** @return array{migrated: bool, tables: list<string>, error: ?string} */
    private function migrateComponent(int $siteId, SchemaComponent $component, string $fromVersion): array
    {
        if ($fromVersion === $component->version) {
            return ['migrated' => false, 'tables' => [], 'error' => null];
        }

        foreach ($component->tables as $table) {
            $this->migrator->create($table);
        }

        foreach ($component->tables as $table) {
            if (! $this->migrator->exists($table->name)) {
                return $this->failedComponent($siteId, $component, $fromVersion, $table->name);
            }
        }

        $this->versions->put($component->optionName, $component->version);

        return [
            'migrated' => true,
            'tables' => array_map(static fn (Table $table): string => $table->name, $component->tables),
            'error' => null,
        ];
    }

    /** @return array{migrated: false, tables: list<string>, error: string} */
    private function failedComponent(
        int $siteId,
        SchemaComponent $component,
        string $fromVersion,
        string $missingTable,
    ): array {
        $error = sprintf(
            'Schema component [%s] did not create table [%s].',
            $component->id,
            $missingTable,
        );
        // The runner owns failure dispatch because the component/version detail is
        // not recoverable from the aggregate SiteMigrationResult returned to callers.
        $this->events->dispatch(new SiteMigrationFailed(
            $siteId,
            $error,
            $component->id,
            $fromVersion,
            $component->version,
        ));

        return ['migrated' => false, 'tables' => [], 'error' => $error];
    }

    /**
     * @param list<string> $componentsMigrated
     * @param list<string> $errors
     */
    private function outcome(array $componentsMigrated, array $errors): SiteMigrationOutcome
    {
        if ($errors !== []) {
            return SiteMigrationOutcome::Failed;
        }

        return $componentsMigrated === []
            ? SiteMigrationOutcome::AlreadyCurrent
            : SiteMigrationOutcome::Migrated;
    }

}
