<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Database\Schema\NetworkMigrationResult;
use Corex\Database\Schema\SiteMigrationOutcome;
use Corex\Database\Schema\SiteMigrationResult;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Events\EventDispatcher;
use Corex\Multisite\Events\SiteMigrated;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\SiteContext;

/**
 * `wp corex migrate` is the thin WP-CLI boundary over SiteMigrationRunner. The
 * pure execute() path owns selection, batching, rows, and exit decisions so the
 * command behavior remains headless-testable (spec 100 FR-028, FR-031).
 */
final class MigrateCommand
{
    public function __construct(
        private readonly SiteMigrationRunner $runner,
        private readonly SiteContext $site,
        private readonly MultisiteContext $multisite,
        private readonly EventDispatcher $events,
    ) {
    }

    /**
     * @param array<int, string> $args
     * @param array<string, string> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $result = $this->execute($assoc);

        if ($result->networkRefused) {
            \WP_CLI::error(__('The --network option requires a WordPress Multisite install.', 'corex'));

            return;
        }

        if ($result->dryRun) {
            \WP_CLI::line(sprintf(
                '[dry-run] %s',
                $result->siteIds === []
                    ? __('No sites have pending schema migrations.', 'corex')
                    : sprintf(
                        /* translators: %s: comma-separated WordPress site ids. */
                        __('Would migrate site ids: %s', 'corex'),
                        implode(', ', $result->siteIds),
                    ),
            ));
        }

        $this->renderRows($result->rows);

        if ($result->shouldExitNonZero()) {
            \WP_CLI::error(__('Schema migration failed for one or more sites.', 'corex'));
        }
    }

    /**
     * @param array<string, string> $assoc
     */
    public function execute(array $assoc): MigrateCommandResult
    {
        $network = isset($assoc['network']);
        $dryRun = isset($assoc['dry-run']);
        $batchSize = isset($assoc['batch']) ? max(1, (int) $assoc['batch']) : 50;

        if ($network && ! $this->multisite->enabled()) {
            return new MigrateCommandResult([], [], $batchSize, $dryRun, networkRefused: true);
        }

        if ($dryRun) {
            return $this->preview($network, $batchSize);
        }

        if (! $network) {
            return $this->migrateCurrentSite($batchSize);
        }

        $networkResult = $this->runNetwork($batchSize);
        $this->announceMigrations($networkResult->results);

        return new MigrateCommandResult(
            array_map(static fn (SiteMigrationResult $result): int => $result->siteId, $networkResult->results),
            array_map($this->row(...), $networkResult->results),
            $batchSize,
            false,
            $networkResult->failed(),
        );
    }

    private function preview(bool $network, int $batchSize): MigrateCommandResult
    {
        $pending = $this->runner->pendingSiteIds();
        $siteIds = $network
            ? $pending
            : (in_array($this->site->id(), $pending, true) ? [$this->site->id()] : []);

        return new MigrateCommandResult(
            $siteIds,
            array_map($this->dryRunRow(...), $siteIds),
            $batchSize,
            true,
        );
    }

    private function migrateCurrentSite(int $batchSize): MigrateCommandResult
    {
        $siteResult = $this->runner->runForSite($this->site->id());
        $this->announceMigration($siteResult);

        return new MigrateCommandResult(
            [$siteResult->siteId],
            [$this->row($siteResult)],
            $batchSize,
            false,
            $siteResult->outcome === SiteMigrationOutcome::Failed ? 1 : 0,
        );
    }

    private function runNetwork(int $batchSize): NetworkMigrationResult
    {
        $offset = 0;
        $results = [];

        do {
            $batch = $this->runner->runForNetwork($batchSize, $offset);
            array_push($results, ...$batch->results);
            $offset = $batch->nextOffset;
        } while (! $batch->complete);

        return new NetworkMigrationResult($results, $offset);
    }

    /** @param list<SiteMigrationResult> $results */
    private function announceMigrations(array $results): void
    {
        foreach ($results as $result) {
            $this->announceMigration($result);
        }
    }

    private function announceMigration(SiteMigrationResult $result): void
    {
        if ($result->outcome !== SiteMigrationOutcome::Migrated) {
            return;
        }

        $this->events->dispatch(new SiteMigrated($result->siteId, $result));

        /**
         * Fires after CoreX has migrated an existing site's registered schema.
         *
         * This WordPress mirror lets site plugins react without depending on the
         * CoreX event dispatcher. No action fires for an already-current no-op.
         *
         * @param int                 $siteId The migrated site id.
         * @param SiteMigrationResult $result The complete migration outcome.
         */
        do_action('corex_site_migrated', $result->siteId, $result);
    }

    /**
     * @return array{site: string, outcome: string, components: string, tables: string, ms: string, error: string}
     */
    private function row(SiteMigrationResult $result): array
    {
        return [
            'site' => (string) $result->siteId,
            'outcome' => $result->outcome->value,
            'components' => $result->componentsMigrated === [] ? '-' : implode(', ', $result->componentsMigrated),
            'tables' => $result->tablesCreated === [] ? '-' : implode(', ', $result->tablesCreated),
            'ms' => number_format($result->durationMs, 2, '.', ''),
            'error' => $result->error ?? '-',
        ];
    }

    /**
     * @return array{site: string, outcome: string, components: string, tables: string, ms: string, error: string}
     */
    private function dryRunRow(int $siteId): array
    {
        return [
            'site' => (string) $siteId,
            'outcome' => 'would-migrate',
            'components' => '-',
            'tables' => '-',
            'ms' => '0.00',
            'error' => '-',
        ];
    }

    /**
     * Uses WP-CLI's formatter when available and the same manual fallback as
     * DoctorCommand, keeping output usable across older WP-CLI installations.
     *
     * @param list<array{site: string, outcome: string, components: string, tables: string, ms: string, error: string}> $rows
     */
    private function renderRows(array $rows): void
    {
        $columns = ['site', 'outcome', 'components', 'tables', 'ms', 'error'];

        if (function_exists('WP_CLI\Utils\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, $columns);
        } else {
            foreach ($rows as $row) {
                \WP_CLI::line(sprintf(
                    /* translators: 1: outcome, 2: site id, 3: components, 4: tables, 5: duration in milliseconds, 6: error. */
                    __('[%1$s] site %2$s — components: %3$s; tables: %4$s; ms: %5$s; error: %6$s', 'corex'),
                    $row['outcome'],
                    $row['site'],
                    $row['components'],
                    $row['tables'],
                    $row['ms'],
                    $row['error'],
                ));
            }
        }
    }
}
