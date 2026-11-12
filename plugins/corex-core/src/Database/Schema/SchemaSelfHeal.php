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
use Corex\Support\BootLogger;

/**
 * Repairs the current site's schema only from admin or cron. This deliberately
 * excludes every WP-CLI bootstrap: commands such as `wp plugin list`, `wp cron
 * event run`, and `wp option get` must not write schema, while explicit tooling
 * already has `wp corex migrate`. FR-035 forbids front-end schema writes;
 * wp_initialize_site covers new sites and admin/cron heal upgrades unattended.
 * WordPress transients are site-scoped, so the lock coordinates per site rather
 * than blocking the whole network. The lock is a mutex held for the duration of
 * the run, not a throttle between runs — the TTL only bounds a crashed request
 * that never reached the finally.
 *
 * The lock is taken only by a request that has found the site behind. Every admin
 * page, every `admin-ajax.php` call (an open admin screen makes one on a timer) and
 * every cron run comes through here, and nearly all of them find the site current:
 * that costs them one read of the registered components' versions and no write. The
 * runner reads the versions again once the lock is held, so a request that looked
 * while another was finishing the same migration does not run it a second time
 * (DECISIONS #360).
 *
 * A component that fails here is logged once and held back on this site for fifteen minutes, so a
 * data step that keeps failing does not rerun with `dbDelta` on every request (spec 124 slice 3).
 */
final class SchemaSelfHeal
{
    /** The transient that holds, per component, when the self-heal may try it again on this site. */
    private const BACKOFF_KEY = 'corex_schema_backoff';

    private const BACKOFF_SECONDS = 900;

    public function __construct(
        private readonly SiteMigrationRunner $runner,
        private readonly SiteContext $site,
        private readonly MultisiteContext $multisite,
        private readonly EventDispatcher $events,
        private readonly BootLogger $logger,
    ) {
    }

    public function run(): void
    {
        if (! (is_admin() || wp_doing_cron())) {
            return;
        }

        $siteId = $this->site->id();

        if (! $this->runner->isDueForSite($siteId)) {
            return;
        }

        $backoff = $this->heldBack();
        $skip = array_keys($backoff);
        if ($skip !== [] && ! $this->runner->isDueForSite($siteId, $skip)) {
            return;
        }

        $lockKey = 'corex_schema_migrating';

        if (get_transient($lockKey)) {
            return;
        }

        set_transient($lockKey, 1, 60);

        try {
            $result = $this->runner->runForSite($siteId, $skip);

            if ($result->componentsFailed !== []) {
                $this->holdBack($siteId, $result, $backoff);
            }

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

    /**
     * The components this site's self-heal is holding back, and until when.
     *
     * @return array<string, int>
     */
    private function heldBack(): array
    {
        $stored = get_transient(self::BACKOFF_KEY);
        if (! is_array($stored)) {
            return [];
        }

        $now = time();

        return array_filter(
            $stored,
            static fn (mixed $until, mixed $component): bool => is_string($component) && is_int($until) && $until > $now,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Says, once, that a component could not be migrated here, and holds it back for a while.
     *
     * A failing data step (or a table that is not made) would otherwise run again, `dbDelta` and
     * all, on every admin page, admin-ajax call and cron run, and nothing would say so. The explicit
     * paths, `wp corex migrate` and a release installed from the admin, are not held back.
     *
     * @param array<string, int> $backoff
     */
    private function holdBack(int $siteId, SiteMigrationResult $result, array $backoff): void
    {
        $until = time() + self::BACKOFF_SECONDS;
        foreach ($result->componentsFailed as $component) {
            $backoff[$component] = $until;
        }
        set_transient(self::BACKOFF_KEY, $backoff, self::BACKOFF_SECONDS);

        $this->logger->error(sprintf(
            'CoreX could not migrate %s on site %d: %s The admin tries again in %d minutes; `wp corex migrate` tries now.',
            implode(', ', $result->componentsFailed),
            $siteId,
            (string) $result->error,
            intdiv(self::BACKOFF_SECONDS, 60),
        ));
    }
}
