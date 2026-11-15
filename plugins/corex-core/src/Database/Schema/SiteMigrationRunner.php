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
 * retry semantics and returning failures that ProviderRepository would only log. A component's
 * data steps run after its tables exist and before its version is stored, so a step that fails
 * leaves the component behind and is run again (spec 124, FR-020).
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

    /**
     * @param list<string> $skip component ids left alone this time: the self-heal's backoff after a
     *                           failure. The explicit paths pass none.
     */
    public function runForSite(int $siteId, array $skip = []): SiteMigrationResult
    {
        $started = microtime(true);

        return $this->scope->run(
            $siteId,
            fn (): SiteMigrationResult => $this->runCurrentSite($siteId, $started, $skip),
        );
    }

    /**
     * Whether a run would have anything to do: a registered component's stored version
     * differs from the one it declares. One read, and no write.
     *
     * It answers from what the request has read, which is enough to decide that a site
     * needs no run. A run that follows reads again (see runCurrentSite()).
     */
    /** @param list<string> $skip component ids not to count */
    public function isDueForSite(int $siteId, array $skip = []): bool
    {
        return $this->scope->run($siteId, fn (): bool => $this->isDue($this->storedVersions(), $skip));
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

    /**
     * @param array<string, string> $stored
     * @param list<string>          $skip
     */
    private function isDue(array $stored, array $skip = []): bool
    {
        foreach ($this->schemas->all() as $component) {
            if (in_array($component->id, $skip, true)) {
                continue;
            }

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

    /** @param list<string> $skip */
    private function runCurrentSite(int $siteId, float $started, array $skip = []): SiteMigrationResult
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
        $componentsFailed = [];
        $tablesCreated = [];
        $errors = [];

        foreach ($this->schemas->all() as $component) {
            if (in_array($component->id, $skip, true)) {
                continue;
            }

            $componentResult = $this->migrateComponent($siteId, $component, $stored[$component->optionName]);

            if ($componentResult['error'] !== null) {
                $errors[] = $componentResult['error'];
                $componentsFailed[] = $component->id;
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
            $componentsFailed,
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
                return $this->failedComponent(
                    $siteId,
                    $component,
                    $fromVersion,
                    sprintf('Schema component [%s] did not create table [%s].', $component->id, $table->name),
                );
            }
        }

        foreach ($component->steps as $step) {
            try {
                $step->run();
            } catch (Throwable $e) {
                return $this->failedComponent(
                    $siteId,
                    $component,
                    $fromVersion,
                    sprintf('Schema component [%s] step [%s] failed: %s', $component->id, $step->id(), $e->getMessage()),
                );
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
        string $error,
    ): array {
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
