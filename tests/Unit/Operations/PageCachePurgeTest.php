<?php

/**
 * A mode change clears the page caches CoreX can clear (spec 124, FR-004b, DECISIONS #524).
 *
 * Contract: on a change of mode, each page-cache plugin that is active on the site is asked to
 * clear its pages through the purge it offers, and one that is not active is left alone; what was
 * cleared, and what could not be, is kept for the activity record of that change; a purge that
 * throws is reported as not cleared and stops neither the other purges nor the change.
 *
 * The tests that need a plugin's purge to exist define a stand-in under a name of their own, so no
 * test leaves a real plugin's function defined for another. The last test checks the real names,
 * each verified against the plugin's own source.
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

it('FR-004b: asks an active plugin to clear its pages through its own purge function, and reports it cleared', function () {
    Functions\expect('corex_test_purge_one')->once()->withNoArgs();

    $purge = new PageCachePurge(['one' => ['label' => 'One Cache', 'function' => 'corex_test_purge_one']]);

    expect($purge->clear())->toBe(['cleared' => ['one'], 'failed' => []]);
});

it('FR-004b: leaves a plugin that is not active alone, and does not report it', function () {
    $purge = new PageCachePurge(['absent' => ['label' => 'Absent', 'function' => 'corex_test_purge_never_defined']]);

    expect($purge->clear())->toBe(['cleared' => [], 'failed' => []]);
});

it('FR-004b: fires a plugin\'s purge action, with its arguments, only when the plugin listens to it', function () {
    add_action('corex_test_purge_action', '__return_null');
    Actions\expectDone('corex_test_purge_action')->once()->with('*');
    Actions\expectDone('corex_test_purge_unheard')->never();

    $purge = new PageCachePurge([
        'heard'   => ['label' => 'Heard', 'action' => 'corex_test_purge_action', 'args' => ['*']],
        'unheard' => ['label' => 'Unheard', 'action' => 'corex_test_purge_unheard'],
    ]);

    expect($purge->clear())->toBe(['cleared' => ['heard'], 'failed' => []]);
});

it('FR-004b: reports a purge that throws as not cleared, and still runs the others', function () {
    Functions\expect('corex_test_purge_throws')->once()->andThrow(new RuntimeException('disk full'));
    Functions\expect('corex_test_purge_after')->once();

    $purge = new PageCachePurge([
        'throws' => ['label' => 'Throws', 'function' => 'corex_test_purge_throws'],
        'after'  => ['label' => 'After', 'function' => 'corex_test_purge_after'],
    ]);

    expect($purge->clear())->toBe(['cleared' => ['after'], 'failed' => ['throws']]);
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

it('FR-004b: clears on a change of mode and keeps what it cleared for the record of that change', function () {
    Functions\expect('corex_test_purge_on_change')->once();
    $purge = new PageCachePurge(['changed' => ['label' => 'Changed', 'function' => 'corex_test_purge_on_change']]);

    expect($purge->lastClearing())->toBeNull();

    $purge->onModeChanged();

    expect($purge->lastClearing())->toBe(['cleared' => ['changed'], 'failed' => []]);
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

// Last, because it defines the real plugins' function names for the rest of this process.
it('FR-004b: purges each supported plugin through the name its own source declares, page cache only', function () {
    Functions\expect('rocket_clean_domain')->once()->withNoArgs();
    // Posts only: `w3tc_flush_all()` also flushes W3 Total Cache's object cache, where CoreX's rate limits can live.
    Functions\expect('w3tc_flush_posts')->once()->withNoArgs();
    Functions\expect('wp_cache_clear_cache')->once()->with(0);
    // `false`: the pages, not the minified CSS and JavaScript.
    Functions\expect('wpfc_clear_all_cache')->once()->withNoArgs();
    // No address: SiteGround's own "purge the home URL and below", without its Memcached flush.
    Functions\expect('sg_cachepress_purge_cache')->once()->withNoArgs();
    // `*` is LiteSpeed's "every page" tag, the same purge its "Purge All - LSCache" makes.
    add_action('litespeed_purge', 'LiteSpeed\Purge::add');
    Actions\expectDone('litespeed_purge')->once()->with('*');

    expect((new PageCachePurge())->clear())->toBe([
        'cleared' => ['wp-rocket', 'w3-total-cache', 'litespeed-cache', 'wp-super-cache', 'wp-fastest-cache', 'sg-cachepress'],
        'failed'  => [],
    ]);
});
