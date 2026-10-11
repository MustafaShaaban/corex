<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SchemaVersionStore;
use Corex\Database\Schema\SiteMigrationOutcome;
use Corex\Database\Schema\SiteMigrationResult;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\SiteScope;
use Throwable;

/**
 * The database step of an installation (spec 107, US3; FR-030 to FR-033, FR-035; plan D11, D19).
 *
 * It runs in the request that finishes an installation, which is answered by the release just
 * put in place: the schema registry it reads is that release's. What changes the schema is the
 * runner the automatic check and `wp corex migrate` use, so there is one way a schema changes.
 * This adds the two things an installation owes its owner: what moved, and a failure that is
 * named when it happens and not found later by its symptoms.
 *
 * When the automatic check got there first (the finishing request never came, and an admin
 * page was opened), the runner finds nothing to do and this says so.
 */
final class ReleaseFinisher
{
    public const FAILED = 'database_change_failed';

    /** How many sites of a network a request brings to the release. */
    private const SITES = 20;

    public function __construct(
        private readonly SiteMigrationRunner $runner,
        private readonly SchemaRegistry $schemas,
        private readonly SchemaVersionStore $versions,
        private readonly SiteScope $scope,
        private readonly NetworkContext $network,
        private readonly MultisiteContext $multisite,
    ) {
    }

    /**
     * Bring this site, or the next sites of a network, to the release's schema.
     *
     * @param int $offset On a network, how many sites earlier requests dealt with; 0 to begin.
     *
     * @return array{changes:list<array{site:int,component:string,from:string,to:string}>,next:int,done:bool}
     *         Each component that moved, with the version it was at (nothing, for one the site
     *         never had) and the version it is at; where the next request starts; and whether
     *         every site has been dealt with.
     *
     * @throws ReleaseRefused When a change failed, naming it. The schema's recorded version is as the runner left it: not moved, for the component that failed.
     */
    public function run(int $offset): array
    {
        $sites   = $this->sitesFrom($offset);
        $changes = [];

        foreach ($sites as $siteId) {
            array_push($changes, ...$this->bring($siteId));
        }

        return [
            'changes' => $changes,
            'next'    => $offset + count($sites),
            'done'    => ! $this->multisite->enabled() || count($sites) < self::SITES,
        ];
    }

    /**
     * @return list<int>
     */
    private function sitesFrom(int $offset): array
    {
        if ($this->multisite->enabled()) {
            return $this->network->siteIds(self::SITES, $offset);
        }

        return $offset === 0 ? [$this->scope->currentSiteId()] : [];
    }

    /**
     * @return list<array{site:int,component:string,from:string,to:string}>
     *
     * @throws ReleaseRefused
     */
    private function bring(int $siteId): array
    {
        $before = $this->scope->run($siteId, fn (): array => $this->versionsNow());

        try {
            $result = $this->runner->runForSite($siteId);
        } catch (Throwable $thrown) {
            throw $this->failed($siteId, $thrown->getMessage());
        }

        if (! $result->succeeded()) {
            throw $this->failed($siteId, (string) $result->error);
        }

        return $this->changesOf($result, $before);
    }

    /**
     * Each component's recorded version on the site in scope, by the component's name.
     *
     * @return array<string,string>
     */
    private function versionsNow(): array
    {
        $versions = [];

        foreach ($this->schemas->all() as $component) {
            $versions[$component->id] = $this->versions->get($component->optionName);
        }

        return $versions;
    }

    /**
     * @param array<string,string> $before
     *
     * @return list<array{site:int,component:string,from:string,to:string}>
     */
    private function changesOf(SiteMigrationResult $result, array $before): array
    {
        if ($result->outcome !== SiteMigrationOutcome::Migrated) {
            return [];
        }

        $changes = [];

        foreach ($result->componentsMigrated as $id) {
            $changes[] = [
                'site'      => $result->siteId,
                'component' => $id,
                'from'      => $before[$id] ?? '',
                'to'        => (string) $this->schemas->get($id)?->version,
            ];
        }

        return $changes;
    }

    private function failed(int $siteId, string $why): ReleaseRefused
    {
        return new ReleaseRefused(self::FAILED, sprintf(
            /* translators: 1: a site's number on a network (1 on a single site). 2: what failed, in the database's or the framework's own words. */
            __('A change to the database did not complete on site %1$d: %2$s The release is not recorded as installed. Going back to the previous release puts its files back; no change to the database is reversed.', 'corex'),
            $siteId,
            $why,
        ));
    }
}
