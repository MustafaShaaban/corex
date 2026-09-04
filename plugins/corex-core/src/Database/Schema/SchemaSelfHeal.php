<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Corex\Multisite\Events\SiteMigrated;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\SiteContext;

/**
 * Repairs the current site's schema only from admin or cron. This deliberately
 * excludes every WP-CLI bootstrap: commands such as `wp plugin list`, `wp cron
 * event run`, and `wp option get` must not write schema, while explicit tooling
 * already has `wp corex migrate`. FR-035 forbids front-end schema writes;
 * wp_initialize_site covers new sites and admin/cron heal upgrades unattended.
 * WordPress transients are site-scoped, so the lock coordinates per site rather
 * than blocking the whole network. The lock is a mutex held for the duration of
 * the run, not a throttle between runs — the TTL only bounds a crashed request
 * that never reached the finally. Each admin request therefore re-reads one
 * non-autoloaded option per registered component, which is parity with the
 * ConfigServiceProvider installer this replaces.
 */
final class SchemaSelfHeal
{
    public function __construct(
        private readonly SiteMigrationRunner $runner,
        private readonly SiteContext $site,
        private readonly MultisiteContext $multisite,
        private readonly EventDispatcher $events,
    ) {
    }

    public function run(): void
    {
        if (! (is_admin() || wp_doing_cron())) {
            return;
        }

        $lockKey = 'corex_schema_migrating';

        if (get_transient($lockKey)) {
            return;
        }

        set_transient($lockKey, 1, 60);

        try {
            $result = $this->runner->runForSite($this->site->id());

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
        } finally {
            delete_transient($lockKey);
        }
    }
}
