<?php

/**
 * The guarantee spec 078 exists to make (T004/T005 / FR-003, SC-002).
 *
 * CoreX stores two security controls as transients: `ThrottleMiddleware` counts requests in
 * `corex_throttle_*`, and `TokenReplayGuard` marks spent captcha tokens in `corex_captcha_seen_*`.
 * The obvious implementation of "clear CoreX's caches" is a sweep of `corex_*`, and on this codebase
 * that sweep resets brute-force protection and re-opens the replay window — at the moment an
 * operator is most likely to run it, because something is already going wrong.
 *
 * These tests are written against **every scope, including ones that do not exist yet**: the
 * assertions iterate `CacheScope::cases()`, so adding a scope without thinking about classification
 * turns them red rather than shipping.
 *
 * @package Corex\Tests\Unit\Cache
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cache\CacheClassification;
use Corex\Cache\CacheEntry;
use Corex\Cache\CacheRegistry;
use Corex\Cache\CacheScope;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value): mixed => $value);

    $this->registry = new CacheRegistry();
});

it('never offers security state as clearable, at any scope', function () {
    // The test this whole spec is for. It iterates every scope that exists, so a new one cannot be
    // added without satisfying it.
    foreach (CacheScope::cases() as $scope) {
        $keys = array_map(
            static fn (CacheEntry $entry): string => $entry->key,
            $this->registry->clearable($scope),
        );

        // One needle per call, and the assertion is on `in_array` rather than `toContain` with a
        // trailing message: Pest reads extra arguments to `toContain` as further NEEDLES, not as a
        // failure message, so `not->toContain( $key, 'some message' )` quietly asserts something
        // other than what it reads like. Found by deliberately misclassifying the throttle entry
        // and watching this test pass anyway.
        expect(in_array('corex_throttle_', $keys, true))
            ->toBeFalse("scope '{$scope->value}' offered the rate-limit counters as clearable");
        expect(in_array('corex_captcha_seen_', $keys, true))
            ->toBeFalse("scope '{$scope->value}' offered the spent-captcha records as clearable");
    }
});

it('never offers anything classified as security state or a record', function () {
    foreach (CacheScope::cases() as $scope) {
        foreach ($this->registry->clearable($scope) as $entry) {
            expect($entry->classification->mayEverBeCleared())
                ->toBeTrue("scope '{$scope->value}' offered a {$entry->classification->value} entry");
        }
    }
});

it('names what it protects, so a report can say what it left alone', function () {
    $protectedKeys = array_map(
        static fn (CacheEntry $entry): string => $entry->key,
        $this->registry->protected(),
    );

    expect(in_array('corex_throttle_', $protectedKeys, true))->toBeTrue()
        ->and(in_array('corex_captcha_seen_', $protectedKeys, true))->toBeTrue();
});

it('does not offer pending confirmations to a routine clear', function () {
    // Removable in principle, but only once the operator has been told it discards work somebody is
    // part-way through (FR-004). Not a default.
    foreach (CacheScope::cases() as $scope) {
        foreach ($this->registry->clearable($scope) as $entry) {
            expect($entry->classification)
                ->not->toBe(CacheClassification::PendingOperation, $scope->value);
        }
    }
});

it('clears both safe entries under the default scope', function () {
    $keys = array_map(
        static fn (CacheEntry $entry): string => $entry->key,
        $this->registry->clearable(CacheScope::Corex),
    );

    expect($keys)->toContain('corex_asset_manifest')
        ->and($keys)->toContain('corex_form_submission_counts')
        // Eight declared entries, two clearable. The old command cleared one.
        ->and($keys)->toHaveCount(2);
});

it('narrows to build metadata under the assets scope', function () {
    $keys = array_map(
        static fn (CacheEntry $entry): string => $entry->key,
        $this->registry->clearable(CacheScope::Assets),
    );

    expect($keys)->toBe(['corex_asset_manifest']);
});

it('acts on no declared entry for the layer-flushing scopes', function () {
    // Runtime, object, page and CDN flush a layer rather than named keys. Returning nothing is
    // correct — and asserting it stops a later change from quietly making them walk the registry.
    foreach ([CacheScope::Runtime, CacheScope::ObjectCache, CacheScope::Page, CacheScope::Cdn] as $scope) {
        expect($this->registry->clearable($scope))->toBe([], $scope->value);
    }
});

it('requires every declared entry to say what it is and how it lives', function () {
    // FR-001. A cached value that cannot answer these is one nobody can reason about later, which
    // is exactly how the throttle counters came to look like cache.
    foreach ($this->registry->all() as $entry) {
        expect($entry->key)->not->toBe('')
            ->and($entry->owner)->not->toBe('', "{$entry->key} has no owner")
            ->and($entry->lifetime)->not->toBe('', "{$entry->key} has no lifetime")
            ->and($entry->invalidation)->not->toBe('', "{$entry->key} has no invalidation path");
    }
});

it('declares every cache call site found in the codebase', function () {
    // Read from evidence/before/cache-inventory.md, which was read from the source. If a cached
    // value exists that is not declared here, no clear path knows about it and no operator can see
    // it — which is the state this spec found the product in.
    $declared = array_map(
        static fn (CacheEntry $entry): string => $entry->key,
        $this->registry->all(),
    );

    expect($declared)->toHaveCount(8)
        ->and($declared)->toContain('corex_asset_manifest')
        ->and($declared)->toContain('corex_form_submission_counts')
        ->and($declared)->toContain('corex_throttle_')
        ->and($declared)->toContain('corex_captcha_seen_')
        ->and($declared)->toContain('corex_data_mutation_preview_')
        ->and($declared)->toContain('corex_migration_preview_')
        ->and($declared)->toContain('corex_submission_bulk_preview_')
        ->and($declared)->toContain('corex_import_preview_');
});

it('drops a malformed declaration rather than treating it as clearable', function () {
    // An add-on filtering in something that is not a CacheEntry must not become an unclassified key
    // that a clear path then assumes is safe.
    Functions\when('apply_filters')->alias(
        static fn (string $hook, mixed $value): mixed => $hook === 'corex_cache_registry'
            ? [...(array) $value, 'corex_rogue_key', null, 42]
            : $value,
    );

    $registry = new CacheRegistry();

    foreach ($registry->all() as $entry) {
        expect($entry)->toBeInstanceOf(CacheEntry::class);
    }

    expect($registry->all())->toHaveCount(8);
});
