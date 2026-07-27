<?php

/**
 * Clearing a cache never removes protection (spec 078, T014–T017 / FR-003, SC-002/003).
 *
 * The unit tests prove the registry refuses to *offer* security state. This proves the thing an
 * operator actually cares about: after running every scope in turn against a real WordPress, a rate
 * limit that was in force is still in force, and a spent captcha token is still spent.
 *
 * Written this way because the failure it guards against is silent. A sweep that deletes
 * `corex_throttle_*` breaks nothing visible — the site keeps working, requests keep succeeding, and
 * the only difference is that the protection is gone.
 *
 * @package Corex\Tests\Integration\Cache
 */

declare(strict_types=1);

use Corex\Cache\CacheManager;
use Corex\Cache\CacheRegistry;
use Corex\Cache\CacheScope;
use Corex\Cache\WordPressCacheStore;

/** The two families that must survive everything, written the way their owners write them. */
const THROTTLE_KEY = 'corex_throttle_' . '098f6bcd4621d373cade4e832627b4f6';
const CAPTCHA_KEY  = 'corex_captcha_seen_' . 'a94a8fe5ccb19ba61c4c0873d391e987';

beforeEach(function () {
    $this->manager = new CacheManager(new WordPressCacheStore(), new CacheRegistry());

    // Real protection state, as ThrottleMiddleware and TokenReplayGuard would leave it.
    set_transient(THROTTLE_KEY, 5, 300);
    set_transient(CAPTCHA_KEY, 1, 150);
    set_transient('corex_asset_manifest', ['build' => 'x'], 0);
});

afterEach(function () {
    delete_transient(THROTTLE_KEY);
    delete_transient(CAPTCHA_KEY);
    delete_transient('corex_asset_manifest');
});

it('leaves rate limits and spent captcha tokens intact after every scope', function () {
    // Every scope, including any added later — the loop is over `cases()`, not a fixed list.
    foreach (CacheScope::cases() as $scope) {
        set_transient(THROTTLE_KEY, 5, 300);
        set_transient(CAPTCHA_KEY, 1, 150);

        $this->manager->clear($scope, true);

        // Cast before comparing, and the reason is worth knowing: without a persistent object
        // cache a transient lives in the options table, so a value written as int 5 and read back
        // through the in-memory cache is int 5 — but read back after a runtime flush it comes from
        // MySQL as the string "5". A strict comparison here fails on the `runtime` scope for a
        // type change rather than for a missing counter, which is a test artifact dressed as a
        // security regression. What matters is that the count is still five.
        expect((int) get_transient(THROTTLE_KEY))
            ->toBe(5, "scope '{$scope->value}' removed the rate-limit counter");
        expect((int) get_transient(CAPTCHA_KEY))
            ->toBe(1, "scope '{$scope->value}' removed the spent-captcha record");
    }
});

it('clears the asset manifest under the default scope', function () {
    $outcome = $this->manager->clear(CacheScope::Corex);

    expect(get_transient('corex_asset_manifest'))->toBeFalse()
        ->and($outcome->cleared)->toContain('corex_asset_manifest')
        ->and($outcome->didSomething())->toBeTrue();
});

it('says what it deliberately left alone, rather than quietly doing less', function () {
    // An operator who cleared "everything" and still sees a rate limit needs to be able to read
    // why here, instead of concluding the button is broken.
    $outcome = $this->manager->clear(CacheScope::Corex);

    expect(array_keys($outcome->skipped))->toContain('corex_throttle_')
        ->and(array_keys($outcome->skipped))->toContain('corex_captcha_seen_')
        ->and($outcome->skipped['corex_throttle_'])->toContain('security state');
});

it('refuses a scope that reaches beyond CoreX until it is asked for explicitly', function () {
    // `wp_cache_flush()` empties the object cache for every plugin on the site. It must not happen
    // because somebody clicked the nearest button.
    $unconfirmed = $this->manager->clear(CacheScope::ObjectCache);

    expect($unconfirmed->didSomething())->toBeFalse()
        ->and(array_keys($unconfirmed->unsupported))->toContain('object');
});

it('reports page and CDN as unsupported rather than pretending', function () {
    foreach ([CacheScope::Page, CacheScope::Cdn] as $scope) {
        $outcome = $this->manager->clear($scope, true);

        expect($outcome->didSomething())->toBeFalse($scope->value)
            ->and(array_keys($outcome->unsupported))->toContain($scope->value);
    }
});

it('never reports a bare success', function () {
    // The command this replaces printed "success" after deleting one transient. Every outcome now
    // has to say something an operator can act on.
    foreach (CacheScope::cases() as $scope) {
        $summary = $this->manager->clear($scope, true)->summary();

        expect($summary)->not->toBe('')
            ->and(strtolower($summary))->not->toBe('success');
    }
});

it('treats an already-absent key as done, not as a failure', function () {
    delete_transient('corex_asset_manifest');

    $outcome = $this->manager->clear(CacheScope::Corex);

    // The desired state is "not cached", and it already held.
    expect($outcome->hasFailures())->toBeFalse()
        ->and($outcome->skipped['corex_asset_manifest'] ?? '')->toContain('not cached');
});

it('removes no stored record', function () {
    // SC-003. Records are data; that some are read through a cache does not make them disposable.
    $id = wp_insert_post([
        'post_type'   => 'corex_submission',
        'post_status' => 'private',
        'post_title'  => 'Cache clearing must not touch this',
    ]);

    foreach (CacheScope::cases() as $scope) {
        $this->manager->clear($scope, true);
    }

    expect(get_post($id))->not->toBeNull()
        ->and(get_post_status($id))->toBe('private');

    wp_delete_post($id, true);
});
