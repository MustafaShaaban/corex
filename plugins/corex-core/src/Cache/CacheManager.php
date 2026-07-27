<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

use Throwable;

defined('ABSPATH') || exit;

/**
 * The one way CoreX clears a cache (spec 078, FR-010/011/013).
 *
 * **`clear()` walks declared entries. It never matches a pattern.** That is the design decision the
 * whole spec rests on: a `delete ... LIKE 'corex_%'` is shorter and would remove every rate-limit
 * counter and every spent-captcha token, because both live in `corex_*` transients. Walking a
 * declared list means the registry's classification is consulted for every removal, so no scope —
 * including one added years from now — can reach security state.
 *
 * Reading and writing go through {@see CacheStore}; this adds the operations that need to know what
 * a value *is* rather than merely where it lives.
 */
final class CacheManager
{
    public function __construct(
        private readonly CacheStore $store,
        private readonly CacheRegistry $registry,
    ) {
    }

    public function store(): CacheStore
    {
        return $this->store;
    }

    public function registry(): CacheRegistry
    {
        return $this->registry;
    }

    /**
     * Clear what this scope covers, and report everything it did not.
     *
     * @param bool $confirmed Whether the operator explicitly asked for a scope that reaches beyond
     *                        CoreX. Required for the object cache, page cache and CDN, because each
     *                        affects software CoreX does not own.
     */
    public function clear(CacheScope $scope, bool $confirmed = false): CacheOutcome
    {
        $outcome = CacheOutcome::for($scope);

        if ($scope->requiresExplicitConfirmation() && ! $confirmed) {
            return $outcome->withUnsupported(
                $scope->value,
                __('This scope reaches beyond CoreX, so it has to be asked for explicitly.', 'corex'),
            );
        }

        $outcome = match ($scope) {
            CacheScope::Runtime     => $this->flushRuntime($outcome),
            CacheScope::ObjectCache => $this->flushObjectCache($outcome),
            CacheScope::Page, CacheScope::Cdn => $outcome->withUnsupported(
                $scope->value,
                __('No provider is configured for this on this site.', 'corex'),
            ),
            default => $this->clearDeclared($scope, $outcome),
        };

        // Always say what was protected. An operator who cleared "everything" and still sees a
        // rate limit should be able to read why here, rather than concluding the button is broken.
        foreach ($this->registry->protected() as $entry) {
            $outcome = $outcome->withSkipped(
                $entry->key,
                $entry->classification->refusalReason(),
            );
        }

        return $outcome;
    }

    /**
     * Remove the entries this scope declares, one at a time.
     *
     * Each removal is attempted independently so one failure does not abandon the rest, and each
     * result is recorded rather than summarised — "3 cleared, 1 failed" is actionable where
     * "partially succeeded" is not.
     */
    private function clearDeclared(CacheScope $scope, CacheOutcome $outcome): CacheOutcome
    {
        foreach ($this->registry->clearable($scope) as $entry) {
            try {
                $removed = $entry->usesObjectCache()
                    ? wp_cache_delete($entry->key, (string) $entry->group)
                    : delete_transient($entry->key);

                // A key that was not there is not a failure: the desired state is "not cached", and
                // it already held.
                $outcome = $removed
                    ? $outcome->withCleared($entry->key)
                    : $outcome->withSkipped($entry->key, __('Was not cached.', 'corex'));
            } catch (Throwable $error) {
                $outcome = $outcome->withFailed($entry->key, $error->getMessage());
            }
        }

        return $outcome;
    }

    private function flushRuntime(CacheOutcome $outcome): CacheOutcome
    {
        // The in-process object cache only. Nothing persistent is touched, which is why this scope
        // needs no confirmation.
        if (function_exists('wp_cache_flush_runtime')) {
            wp_cache_flush_runtime();

            return $outcome->withCleared(__('This request’s object cache', 'corex'));
        }

        return $outcome->withUnsupported(
            'runtime',
            __('This WordPress version cannot flush the request cache on its own.', 'corex'),
        );
    }

    private function flushObjectCache(CacheOutcome $outcome): CacheOutcome
    {
        if (! function_exists('wp_cache_flush')) {
            return $outcome->withUnsupported('object', __('No object cache is available.', 'corex'));
        }

        /*
         * Refused when WordPress is using a persistent object cache — and this is the one place in
         * the feature where the registry's guarantee is not enough on its own.
         *
         * With a persistent object cache installed, WordPress stores transients *in that cache*.
         * `wp_cache_flush()` would therefore delete `corex_throttle_*` and `corex_captcha_seen_*`
         * as collateral: not by walking them, not by matching them, but by emptying the place they
         * happen to live. FR-003 says no cache operation may remove security state, and an
         * operation that removes it indirectly still removes it.
         *
         * So CoreX declines, and says why. An operator who genuinely wants the object cache emptied
         * can still run `wp cache flush` — that is WordPress's own operation, with its own
         * consequences, and it is not CoreX claiming the result was safe.
         *
         * Found because a test asserting the counters survive every scope failed for an unrelated
         * reason on a site with no persistent object cache, and asking why exposed what would
         * happen on a site that had one.
         */
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            return $outcome->withUnsupported(
                'object',
                __('This site keeps transients in its object cache, so flushing it would also remove CoreX rate limits and spent-token records. Run `wp cache flush` directly if that is what you intend.', 'corex'),
            );
        }

        // Without a persistent object cache there is nothing durable to lose: the flush clears the
        // in-request array, and transients stay in the options table where they were written.
        return wp_cache_flush()
            ? $outcome->withCleared(__('The object cache', 'corex'))
            : $outcome->withFailed('object', __('The object cache refused the flush.', 'corex'));
    }
}
