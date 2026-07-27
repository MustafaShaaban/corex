<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * Everything CoreX caches, and what each of those things is (spec 078, FR-001/003).
 *
 * **This is the only place that decides what a clear operation may remove**, and it decides by
 * consulting a declaration rather than by matching a pattern. That distinction is the whole design.
 *
 * A pattern-matching clear — `DELETE ... LIKE 'corex_%'`, or `foreach transients starting corex_` —
 * is one line shorter and would delete every rate-limit counter and every spent-captcha token,
 * because both are stored as `corex_*` transients. Worse, it would keep doing so silently as new
 * entries appear. Walking a declared list means a key nobody declared is a key nothing clears, and
 * an entry classified as security state cannot be reached by any scope, present or future.
 *
 * Adding a cached value to CoreX means adding it here. That is deliberate friction.
 */
final class CacheRegistry
{
    /** @var list<CacheEntry>|null */
    private ?array $entries = null;

    /**
     * @return list<CacheEntry>
     */
    public function all(): array
    {
        if ($this->entries !== null) {
            return $this->entries;
        }

        $entries = [
            new CacheEntry(
                key: 'corex_asset_manifest',
                isPrefix: false,
                owner: 'Corex\\Assets\\AssetManager',
                classification: CacheClassification::GeneratedMetadata,
                lifetime: __('Until the build changes', 'corex'),
                invalidation: __('Rebuilt from build/manifest.json on the next request.', 'corex'),
            ),
            new CacheEntry(
                key: 'corex_form_submission_counts',
                isPrefix: false,
                owner: 'Corex\\Config\\Forms\\WpSubmissionCounts',
                classification: CacheClassification::SafeCache,
                lifetime: __('One minute', 'corex'),
                invalidation: __('Recounted from submissions on the next read.', 'corex'),
                group: 'corex',
            ),
            new CacheEntry(
                key: 'corex_throttle_',
                isPrefix: true,
                owner: 'Corex\\Http\\Middleware\\ThrottleMiddleware',
                classification: CacheClassification::SecurityState,
                lifetime: __('The rate-limit window', 'corex'),
                invalidation: __('Expires with the window. Never cleared by hand — clearing it removes the limit.', 'corex'),
            ),
            new CacheEntry(
                key: 'corex_captcha_seen_',
                isPrefix: true,
                owner: 'Corex\\Captcha\\TokenReplayGuard',
                classification: CacheClassification::SecurityState,
                lifetime: __('The captcha token window', 'corex'),
                invalidation: __('Expires with the token. Clearing it would allow a spent token to be replayed.', 'corex'),
            ),
            new CacheEntry(
                key: 'corex_data_mutation_preview_',
                isPrefix: true,
                owner: 'Corex\\Config\\Data\\WpDataMutationPreviewStore',
                classification: CacheClassification::PendingOperation,
                lifetime: __('Until confirmed or expired', 'corex'),
                invalidation: __('Consumed when the change is applied.', 'corex'),
            ),
            new CacheEntry(
                key: 'corex_migration_preview_',
                isPrefix: true,
                owner: 'Corex\\Config\\DataModels\\WpMigrationPreviewStore',
                classification: CacheClassification::PendingOperation,
                lifetime: __('Until confirmed or expired', 'corex'),
                invalidation: __('Consumed when the migration is applied.', 'corex'),
            ),
            new CacheEntry(
                key: 'corex_submission_bulk_preview_',
                isPrefix: true,
                owner: 'Corex\\Config\\Submissions\\WpSubmissionBulkPreviewStore',
                classification: CacheClassification::PendingOperation,
                lifetime: __('Until confirmed or expired', 'corex'),
                invalidation: __('Consumed when the bulk action is applied.', 'corex'),
            ),
            new CacheEntry(
                key: 'corex_import_preview_',
                isPrefix: true,
                owner: 'Corex\\Config\\DataModels\\DataModelsImportController',
                classification: CacheClassification::PendingOperation,
                lifetime: __('Until confirmed or expired', 'corex'),
                invalidation: __('Consumed when the import is applied.', 'corex'),
            ),
        ];

        /**
         * Let an add-on declare its own cached values.
         *
         * Filtered rather than hard-coded so a kit or add-on can be honest about what it caches —
         * and so anything it declares is subject to the same classification rules. Entries that are
         * not `CacheEntry` instances are dropped: a malformed declaration must not become an
         * unclassified key that a clear path then treats as safe.
         *
         * @param list<CacheEntry> $entries The declared cache inventory.
         */
        $filtered = apply_filters('corex_cache_registry', $entries);

        $this->entries = array_values(array_filter(
            is_array($filtered) ? $filtered : $entries,
            static fn (mixed $entry): bool => $entry instanceof CacheEntry,
        ));

        return $this->entries;
    }

    /**
     * The entries a given scope is allowed to remove.
     *
     * Classification decides first and the scope narrows afterwards — never the reverse. A scope
     * cannot widen its way into security state, because security state is filtered out before the
     * scope is even considered.
     *
     * @return list<CacheEntry>
     */
    public function clearable(CacheScope $scope): array
    {
        $routine = array_values(array_filter(
            $this->all(),
            static fn (CacheEntry $entry): bool => $entry->isRoutinelyClearable(),
        ));

        return match ($scope) {
            CacheScope::Assets => array_values(array_filter(
                $routine,
                static fn (CacheEntry $entry): bool
                    => $entry->classification === CacheClassification::GeneratedMetadata,
            )),
            CacheScope::Corex, CacheScope::ExpiredTransients => $routine,
            // Runtime, object, page and CDN do not act on declared entries at all — they flush a
            // layer. Returning nothing here is correct, not an omission.
            default => [],
        };
    }

    /**
     * Entries a scope may not touch, with the reason — so a report can say what it left alone
     * rather than quietly doing less than the operator expected.
     *
     * @return list<CacheEntry>
     */
    public function protected(): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (CacheEntry $entry): bool => ! $entry->classification->mayEverBeCleared(),
        ));
    }

    public function find(string $key): ?CacheEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->key === $key) {
                return $entry;
            }
        }

        return null;
    }
}
