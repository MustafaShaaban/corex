<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache\Status;

use Corex\Cache\CacheStore;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Every caching layer on this site, from real checks (spec 078, FR-016/017/021, SC-005).
 *
 * Each layer is probed rather than assumed. That sounds obvious and is the requirement most easily
 * failed: "Redis is installed" is a fact about the server, and the fact that matters is whether
 * *WordPress* is using it. A status screen that conflates the two tells an operator their site is
 * faster than it is, and there is no error anywhere to correct the impression.
 */
final class CacheStatusReport
{
    public function __construct(private readonly CacheStore $store)
    {
    }

    /**
     * @return list<CacheLayer>
     */
    public function layers(): array
    {
        $now = gmdate(DATE_ATOM);

        return [
            $this->browser($now),
            $this->opcache($now),
            $this->requestCache($now),
            $this->objectCache($now),
            $this->application($now),
            $this->pageCache($now),
            $this->cdn($now),
        ];
    }

    /**
     * The visitor's own browser — listed precisely because CoreX cannot touch it.
     *
     * This is the layer people mean when they say "clear the cache" and the site still looks old,
     * and no admin screen anywhere can empty it. Saying so beside the mechanism that *does* solve
     * the problem is more use than leaving the layer out and letting them keep looking for a button.
     */
    private function browser(string $now): CacheLayer
    {
        return new CacheLayer(
            key: 'browser',
            name: __('Visitor browsers', 'corex'),
            purpose: __('Browsers keep copies of CSS, JavaScript and images so pages load faster.', 'corex'),
            state: CacheLayerState::Active,
            provider: __('The visitor’s browser', 'corex'),
            manageable: false,
            safeToClear: false,
            detail: __('No website can empty a visitor’s browser cache. CoreX changes the version on each asset when it changes, which is what makes browsers fetch the new file instead of reusing the old one.', 'corex'),
            checkedAt: $now,
        );
    }

    /**
     * PHP's compiled-code cache.
     *
     * `unknown` when inspection is disabled, which many hosts do. Reporting "off" because we were
     * not permitted to look would be a confident wrong answer, and an operator acting on it would
     * be chasing a problem that does not exist.
     */
    private function opcache(string $now): CacheLayer
    {
        if (! function_exists('opcache_get_status')) {
            return new CacheLayer(
                key: 'opcache',
                name: __('PHP OPcache', 'corex'),
                purpose: __('PHP keeps compiled code in memory so it is not recompiled on every request.', 'corex'),
                state: CacheLayerState::Unknown,
                provider: '',
                manageable: false,
                safeToClear: false,
                detail: __('This host does not allow CoreX to inspect OPcache, so its state cannot be reported. That is a hosting setting, not a fault.', 'corex'),
                checkedAt: $now,
            );
        }

        try {
            $status = @opcache_get_status(false);
        } catch (Throwable) {
            $status = false;
        }

        if (! is_array($status)) {
            return new CacheLayer(
                key: 'opcache',
                name: __('PHP OPcache', 'corex'),
                purpose: __('PHP keeps compiled code in memory so it is not recompiled on every request.', 'corex'),
                state: CacheLayerState::Unknown,
                provider: '',
                manageable: false,
                safeToClear: false,
                detail: __('OPcache did not report a status. It may be disabled, or restricted on this host.', 'corex'),
                checkedAt: $now,
            );
        }

        $enabled = ! empty($status['opcache_enabled']);
        $hits    = (int) ($status['opcache_statistics']['hits'] ?? 0);
        $misses  = (int) ($status['opcache_statistics']['misses'] ?? 0);
        $total   = $hits + $misses;

        return new CacheLayer(
            key: 'opcache',
            name: __('PHP OPcache', 'corex'),
            purpose: __('PHP keeps compiled code in memory so it is not recompiled on every request.', 'corex'),
            state: $enabled ? CacheLayerState::Active : CacheLayerState::Available,
            provider: 'OPcache',
            // Resetting OPcache on a live site briefly slows every request while PHP recompiles.
            // That belongs to a deployment step, not to a button somebody presses while debugging.
            manageable: false,
            safeToClear: false,
            detail: $enabled && $total > 0
                ? sprintf(
                    /* translators: %s: percentage of OPcache lookups that were hits. */
                    __('Serving %s%% of lookups from memory. Reset belongs to your deployment process, not to this screen.', 'corex'),
                    number_format_i18n(($hits / $total) * 100, 1),
                )
                : __('Reset belongs to your deployment process, not to this screen.', 'corex'),
            checkedAt: $now,
        );
    }

    private function requestCache(string $now): CacheLayer
    {
        return new CacheLayer(
            key: 'request',
            name: __('This request’s cache', 'corex'),
            purpose: __('WordPress remembers values within a single page load so the same query is not run twice.', 'corex'),
            state: CacheLayerState::Active,
            provider: 'WordPress',
            manageable: true,
            safeToClear: true,
            detail: __('Always present, and gone when the request ends. Clearing it affects nothing beyond the current page load.', 'corex'),
            checkedAt: $now,
        );
    }

    /**
     * A persistent object cache — Redis, Memcached, or another drop-in.
     *
     * The question asked is whether **WordPress** is using one, via `wp_using_ext_object_cache()`.
     * A running Redis container that no drop-in connects to makes no difference to this site, and
     * reporting it as active would be reporting the server's state as the site's.
     */
    private function objectCache(string $now): CacheLayer
    {
        $inUse   = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
        $dropIn  = defined('WP_CONTENT_DIR') && file_exists(WP_CONTENT_DIR . '/object-cache.php');

        if ($inUse) {
            return new CacheLayer(
                key: 'object',
                name: __('Persistent object cache', 'corex'),
                purpose: __('Keeps database results in memory between requests, which is the single biggest speed gain on a busy site.', 'corex'),
                state: CacheLayerState::Active,
                provider: __('Object cache drop-in', 'corex'),
                manageable: true,
                // Flushing it empties the cache for every plugin on the site, and on this
                // configuration transients live here too — which includes CoreX's rate limits.
                safeToClear: false,
                detail: __('WordPress keeps transients here as well, so flushing this would also remove CoreX rate limits and spent-token records. CoreX will not do that; run `wp cache flush` yourself if you intend it.', 'corex'),
                checkedAt: $now,
            );
        }

        return new CacheLayer(
            key: 'object',
            name: __('Persistent object cache', 'corex'),
            purpose: __('Keeps database results in memory between requests, which is the single biggest speed gain on a busy site.', 'corex'),
            state: $dropIn ? CacheLayerState::Available : CacheLayerState::NotDetected,
            provider: $dropIn ? __('Drop-in present but not in use', 'corex') : '',
            manageable: false,
            safeToClear: false,
            detail: $dropIn
                ? __('An object-cache drop-in is installed but WordPress is not using it. A cache that is present is not a cache that is running.', 'corex')
                : __('Not installed, and not required. CoreX works fully without one — this is the normal setup on shared hosting.', 'corex'),
            checkedAt: $now,
        );
    }

    private function application(string $now): CacheLayer
    {
        return new CacheLayer(
            key: 'application',
            name: __('CoreX application cache', 'corex'),
            purpose: __('CoreX’s own cached values — build metadata and counts it would otherwise recompute.', 'corex'),
            state: CacheLayerState::Active,
            provider: $this->store->describe(),
            manageable: true,
            safeToClear: true,
            detail: __('Safe to clear at any time. Everything here is rebuilt on the next request, and CoreX’s rate limits and pending confirmations are deliberately excluded.', 'corex'),
            checkedAt: $now,
        );
    }

    /**
     * A page cache, detected rather than provided.
     *
     * CoreX does not implement one — FR-022 — so this reports what is there and offers a purge only
     * when something real is behind it.
     */
    private function pageCache(string $now): CacheLayer
    {
        /**
         * A page-cache provider may register itself here.
         *
         * @param array{name:string,active:bool}|null $provider
         */
        $provider = apply_filters('corex_page_cache_provider', null);
        $detected = is_array($provider) && ($provider['name'] ?? '') !== '';

        return new CacheLayer(
            key: 'page',
            name: __('Page cache', 'corex'),
            purpose: __('Serves whole pages without running WordPress, for visitors who are not signed in.', 'corex'),
            state: $detected
                ? (! empty($provider['active']) ? CacheLayerState::Active : CacheLayerState::Available)
                : CacheLayerState::NotDetected,
            provider: $detected ? (string) $provider['name'] : '',
            manageable: $detected,
            safeToClear: $detected,
            detail: $detected
                ? __('Purging is handled by the provider that registered itself.', 'corex')
                : __('No page cache detected. CoreX does not provide one — it integrates with whichever you choose.', 'corex'),
            checkedAt: $now,
        );
    }

    /**
     * A CDN, detected rather than assumed.
     *
     * Nothing here reuses a credential granted for another purpose. FR-023 names that explicitly
     * because it is the shortcut available: CoreX already holds a Cloudflare token for Insights,
     * and using it to purge would be using a key for a door it was not cut for.
     */
    private function cdn(string $now): CacheLayer
    {
        /**
         * A CDN provider may register itself here.
         *
         * @param array{name:string,active:bool}|null $provider
         */
        $provider = apply_filters('corex_cdn_provider', null);
        $detected = is_array($provider) && ($provider['name'] ?? '') !== '';

        return new CacheLayer(
            key: 'cdn',
            name: __('CDN', 'corex'),
            purpose: __('Serves assets from servers near your visitors.', 'corex'),
            state: $detected
                ? (! empty($provider['active']) ? CacheLayerState::Active : CacheLayerState::Available)
                : CacheLayerState::NotDetected,
            provider: $detected ? (string) $provider['name'] : '',
            manageable: $detected,
            safeToClear: $detected,
            detail: $detected
                ? __('Purging is handled by the provider that registered itself.', 'corex')
                : __('No CDN configured for cache purging. CoreX will not reuse a credential granted for anything else.', 'corex'),
            checkedAt: $now,
        );
    }
}
