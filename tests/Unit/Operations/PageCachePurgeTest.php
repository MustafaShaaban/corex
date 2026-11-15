<?php

/**
 * A mode change clears the page caches CoreX can clear (spec 124, FR-004b, DECISIONS #524).
 *
 * Contract: on a change of mode, each page-cache plugin that is active on the site is asked to
 * clear its pages through the purge it offers, and one that is not active is left alone; which were
 * asked, and which failed, is kept for the activity record of that change; a purge that throws is
 * reported as failed and stops neither the other purges nor the change.
 *
 * A purge that answers `false`, or WP Fastest Cache's `['success' => false]`, is reported as not
 * cleared too (DECISIONS #625).
 *
 * No test here defines a real plugin's function. Brain Monkey defines a function it is asked to
 * stub for the rest of the process, so a test that stubbed `rocket_clean_domain` would leave every
 * later `PageCachePurge` built with the real table believing WP Rocket active, and calling a stub
 * that throws. Every stand-in has a name of its own (`corex_test_...`), and the real table is
 * checked by its names and by running it with each function renamed.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Corex\Config\Operations\PageCachePurge;

beforeEach(function () {
    Functions\when('is_multisite')->justReturn(false);
});

it('FR-004b: asks an active plugin to clear its pages through its own purge function, and reports it asked', function () {
    Functions\expect('corex_test_purge_one')->once()->withNoArgs();

    $purge = new PageCachePurge(['one' => ['label' => 'One Cache', 'function' => 'corex_test_purge_one']]);

    expect($purge->clear())->toBe(['asked' => ['one'], 'failed' => []]);
});

it('FR-004b: leaves a plugin that is not active alone, and does not report it', function () {
    $purge = new PageCachePurge(['absent' => ['label' => 'Absent', 'function' => 'corex_test_purge_never_defined']]);

    expect($purge->clear())->toBe(['asked' => [], 'failed' => []]);
});

it('FR-004b: fires a plugin\'s purge action, with its arguments, only when the plugin listens to it', function () {
    add_action('corex_test_purge_action', '__return_null');
    Actions\expectDone('corex_test_purge_action')->once()->with('*');
    Actions\expectDone('corex_test_purge_unheard')->never();

    $purge = new PageCachePurge([
        'heard'   => ['label' => 'Heard', 'action' => 'corex_test_purge_action', 'args' => ['*']],
        'unheard' => ['label' => 'Unheard', 'action' => 'corex_test_purge_unheard'],
    ]);

    expect($purge->clear())->toBe(['asked' => ['heard'], 'failed' => []]);
});

it('FR-004b: reports a purge that throws as not cleared, and still runs the others', function () {
    Functions\expect('corex_test_purge_throws')->once()->andThrow(new RuntimeException('disk full'));
    Functions\expect('corex_test_purge_after')->once();

    $purge = new PageCachePurge([
        'throws' => ['label' => 'Throws', 'function' => 'corex_test_purge_throws'],
        'after'  => ['label' => 'After', 'function' => 'corex_test_purge_after'],
    ]);

    expect($purge->clear())->toBe(['asked' => ['after'], 'failed' => ['throws']]);
});

it('FR-004b: clears the whole cache on a single site', function () {
    Functions\expect('corex_test_purge_single_site')->once()->with(0);

    (new PageCachePurge(['site' => ['label' => 'Site', 'function' => 'corex_test_purge_single_site', 'args' => [PageCachePurge::THIS_SITE]]]))->clear();
});

it('FR-004b: clears this site only on a network, whose other sites have modes of their own', function () {
    Functions\when('is_multisite')->justReturn(true);
    Functions\when('get_current_blog_id')->justReturn(3);
    Functions\expect('corex_test_purge_network_site')->once()->with(3);

    (new PageCachePurge(['site' => ['label' => 'Site', 'function' => 'corex_test_purge_network_site', 'args' => [PageCachePurge::THIS_SITE]]]))->clear();
});

it('FR-004b (amended): reports a purge that answers false as not cleared', function () {
    Functions\expect('corex_test_purge_false')->once()->andReturn(false);
    Functions\expect('corex_test_purge_null')->once()->andReturn(null);
    Functions\expect('corex_test_purge_true')->once()->andReturn(true);

    $purge = new PageCachePurge([
        'false' => ['label' => 'False', 'function' => 'corex_test_purge_false'],
        'null'  => ['label' => 'Null', 'function' => 'corex_test_purge_null'],
        'true'  => ['label' => 'True', 'function' => 'corex_test_purge_true'],
    ]);

    expect($purge->clear())->toBe(['asked' => ['null', 'true'], 'failed' => ['false']]);
});

it('FR-004b (amended): reports WP Fastest Cache\'s refusal, an answer with success false, as not cleared', function () {
    Functions\expect('corex_test_purge_refused')->once()->andReturn(['success' => false, 'message' => 'Clearing Cache Hook system has been disabled']);
    Functions\expect('corex_test_purge_succeeded')->once()->andReturn(['success' => true]);

    $purge = new PageCachePurge([
        'refused'   => ['label' => 'Refused', 'function' => 'corex_test_purge_refused'],
        'succeeded' => ['label' => 'Succeeded', 'function' => 'corex_test_purge_succeeded'],
    ]);

    expect($purge->clear())->toBe(['asked' => ['succeeded'], 'failed' => ['refused']]);
});

it('FR-004b (amended): leaves out of the failures a plugin whose false does not mean it failed', function () {
    Functions\expect('corex_test_purge_false_ok')->once()->andReturn(false);

    $purge = new PageCachePurge(['quiet' => ['label' => 'Quiet', 'function' => 'corex_test_purge_false_ok', 'falseIsFailure' => false]]);

    expect($purge->clear())->toBe(['asked' => ['quiet'], 'failed' => []]);
});

it('FR-004b (amended): gives a purge this site\'s own address where it asks for one', function () {
    Functions\when('home_url')->alias(static fn (string $path = ''): string => 'https://example.test/sub' . $path);
    Functions\expect('corex_test_purge_address')->once()->with('https://example.test/sub/');

    (new PageCachePurge(['site' => ['label' => 'Site', 'function' => 'corex_test_purge_address', 'args' => [PageCachePurge::THIS_SITE_URL]]]))->clear();
});

it('FR-004b: clears on a change of mode and keeps what it asked for the record of that change', function () {
    Functions\expect('corex_test_purge_on_change')->once();
    $purge = new PageCachePurge(['changed' => ['label' => 'Changed', 'function' => 'corex_test_purge_on_change']]);

    expect($purge->lastClearing())->toBeNull();

    $purge->onModeChanged();

    expect($purge->lastClearing())->toBe(['asked' => ['changed'], 'failed' => []]);
});

it('FR-004b: listens to the mode store\'s change, before the activity record of it is written', function () {
    $purge = new PageCachePurge([]);
    Functions\expect('add_action')->once()->with('corex_operations_mode_changed', [$purge, 'onModeChanged'], PageCachePurge::PRIORITY, 0);

    $purge->register();

    // The activity recorder listens at WordPress's default, 10.
    expect(PageCachePurge::PRIORITY)->toBeLessThan(10);
});

it('FR-004b: names each plugin by its product name, and an unknown one by its key', function () {
    expect(PageCachePurge::label('wp-rocket'))->toBe('WP Rocket')
        ->and(PageCachePurge::label('litespeed-cache'))->toBe('LiteSpeed Cache')
        ->and(PageCachePurge::label('sg-cachepress'))->toBe('SiteGround Optimizer')
        ->and(PageCachePurge::label('something-else'))->toBe('something-else');
});

it('FR-004b: purges each supported plugin through the name its own source declares, page cache only', function () {
    expect(array_map(
        static fn (array $plugin): array => [$plugin['function'] ?? $plugin['action'], $plugin['args'] ?? []],
        PageCachePurge::PLUGINS,
    ))->toBe([
        'wp-rocket'        => ['rocket_clean_domain', []],
        // Posts only: `w3tc_flush_all()` also flushes W3 Total Cache's object cache, where CoreX's rate limits can live.
        'w3-total-cache'   => ['w3tc_flush_posts', []],
        // `*` is LiteSpeed's "every page" tag, the same purge its "Purge All - LSCache" makes.
        'litespeed-cache'  => ['litespeed_purge', ['*']],
        'wp-super-cache'   => ['wp_cache_clear_cache', [PageCachePurge::THIS_SITE]],
        // No argument: `false`, the pages, not the minified CSS and JavaScript.
        'wp-fastest-cache' => ['wpfc_clear_all_cache', []],
        // This site's address: with none, SiteGround purges `get_home_url('/')`, the main site of a network.
        'sg-cachepress'    => ['sg_cachepress_purge_cache', [PageCachePurge::THIS_SITE_URL]],
    ])
        ->and(PageCachePurge::PLUGINS['litespeed-cache'])->toHaveKey('action');
});

it('FR-004b: runs the real table with the arguments each plugin is given, without defining any plugin function', function () {
    Functions\when('home_url')->alias(static fn (string $path = ''): string => 'https://example.test' . $path);
    $renamed = [];
    foreach (PageCachePurge::PLUGINS as $key => $plugin) {
        if (isset($plugin['function'])) {
            $plugin['function'] = 'corex_test_real_' . $plugin['function'];
        }
        $renamed[$key] = $plugin;
    }
    Functions\expect('corex_test_real_rocket_clean_domain')->once()->withNoArgs();
    Functions\expect('corex_test_real_w3tc_flush_posts')->once()->withNoArgs();
    Functions\expect('corex_test_real_wp_cache_clear_cache')->once()->with(0);
    Functions\expect('corex_test_real_wpfc_clear_all_cache')->once()->withNoArgs();
    // SiteGround answers false when its file cache is on and already gone; that is not a failure.
    Functions\expect('corex_test_real_sg_cachepress_purge_cache')->once()->with('https://example.test/')->andReturn(false);
    add_action('litespeed_purge', 'LiteSpeed\Purge::add');
    Actions\expectDone('litespeed_purge')->once()->with('*');

    expect((new PageCachePurge($renamed))->clear())->toBe([
        'asked' => ['wp-rocket', 'w3-total-cache', 'litespeed-cache', 'wp-super-cache', 'wp-fastest-cache', 'sg-cachepress'],
        'failed'  => [],
    ])
        ->and(function_exists('rocket_clean_domain'))->toBeFalse()
        ->and(function_exists('sg_cachepress_purge_cache'))->toBeFalse();
});