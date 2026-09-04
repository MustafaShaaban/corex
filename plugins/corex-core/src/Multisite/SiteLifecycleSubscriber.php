<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

use Corex\Database\Schema\SchemaMigrator;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SiteMigrationRunner;
use Corex\Events\EventDispatcher;
use Corex\Hooks\SubscribesToHooks;
use Corex\Multisite\Events\SiteCreated;
use Corex\Multisite\Events\SiteDeleted;
use Corex\Multisite\Events\SiteDeleting;
use Corex\Multisite\Events\SiteSchemaInstalled;

/**
 * Joins CoreX schema work to WordPress's site lifecycle without leaking WP_Site
 * objects into framework events. HookRegistry uses add_filter for this seam because
 * WordPress actions are filters with ignored return values (spec 100 FR-036–FR-040).
 */
final class SiteLifecycleSubscriber implements SubscribesToHooks
{
    public function __construct(
        private readonly SiteMigrationRunner $runner,
        private readonly SchemaMigrator $migrator,
        private readonly SchemaRegistry $schemas,
        private readonly EventDispatcher $events,
    ) {
    }

    public function hooks(): array
    {
        return [
            'wp_initialize_site' => ['onSiteInitialized', 20, 2],
            'wp_delete_site' => ['onSiteDeleted', 10, 1],
            'wpmu_drop_tables' => ['filterDropTables', 10, 2],
        ];
    }

    /**
     * Priority 20 runs after WordPress's priority-10 default, which creates the core
     * tables and options for the new site and then puts the current blog back:
     * `wp_initialize_site()` ends with `if ($switch) { restore_current_blog(); }`.
     * So the current site here is the one that *requested* the creation, not the new
     * one — every schema call below must stay inside SiteScope::run(), which performs
     * the real switch. Do not "optimise" that wrapper away.
     *
     * @param array<string, mixed> $args
     */
    public function onSiteInitialized(\WP_Site $site, array $args): void
    {
        $siteId = (int) $site->blog_id;
        $networkId = (int) $site->site_id;
        $event = new SiteCreated(
            $siteId,
            $networkId,
            (string) get_site_url($siteId),
            (string) $site->domain,
            (string) $site->path,
            is_main_site($siteId, $networkId),
        );

        $this->events->dispatch($event);

        /**
         * Fires after WordPress has initialized a site and CoreX has announced it.
         *
         * Site plugins can use this mirror without taking a dependency on CoreX's
         * internal event dispatcher. Schema installation follows this action.
         *
         * @param int $siteId The new site id.
         */
        do_action('corex_site_created', $siteId);

        $result = $this->runner->runForSite($siteId);
        $this->events->dispatch(new SiteSchemaInstalled($siteId, $result));
    }

    public function onSiteDeleted(\WP_Site $site): void
    {
        $siteId = (int) $site->blog_id;
        $this->events->dispatch(new SiteDeleted($siteId));

        /**
         * Fires after WordPress has deleted a site and CoreX has announced it.
         *
         * The tables have already been handled through wpmu_drop_tables; this mirror
         * exists for site plugins that do not consume CoreX framework events.
         *
         * @param int $siteId The deleted site id.
         */
        do_action('corex_site_deleted', $siteId);
    }

    /**
     * WordPress owns the actual drops, their order, and their error handling. CoreX
     * only contributes its site-prefixed names to the filter, preserving every
     * incoming table and avoiding duplicate entries (spec 100 FR-037).
     *
     * @param list<string> $tables
     * @return list<string>
     */
    public function filterDropTables(array $tables, int $siteId): array
    {
        // wpmu_drop_tables only runs when WordPress is actually dropping site tables.
        $this->events->dispatch(new SiteDeleting($siteId, true));

        foreach ($this->schemas->tableNames() as $name) {
            $table = $this->migrator->fullName($name);

            if (! in_array($table, $tables, true)) {
                $tables[] = $table;
            }
        }

        return $tables;
    }
}
