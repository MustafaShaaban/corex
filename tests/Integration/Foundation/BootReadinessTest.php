<?php

/**
 * A site plugin can reach Corex without guessing at load order (spec 086).
 *
 * Found by standing up a plugin that followed `docs-app/.../user-guides.md` exactly. Corex boots on
 * `plugins_loaded` at priority 10 and the generated site starter boots there too, so which runs
 * first depends on the plugin's **directory name**. The loser called `Corex::make()`, reached
 * `Boot::app()`, and got a `RuntimeException` — not a silent no-op: a fatal, on every request,
 * taking the whole site down.
 *
 * And it could not be guarded against. `app()` throws rather than returning null, so
 * `Boot::app() === null` — the check a developer naturally writes — *is* the crash.
 *
 * @package Corex\Tests\Integration\Foundation
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Support\Facades\Corex;

it('answers whether it has booted, without throwing to say so', function () {
    // The predicate that did not exist. Its absence is why the trap had no safe workaround.
    expect(Boot::booted())->toBeTrue();
});

it('runs an onReady callback immediately once booted', function () {
    $ran = 0;

    Corex::onReady(static function () use (&$ran): void {
        $ran++;
    });

    expect($ran)->toBe(1);
});

it('hands the booted application to the callback', function () {
    $received = null;

    Corex::onReady(static function ($app) use (&$received): void {
        $received = $app;
    });

    expect($received)->toBe(Boot::app());
});

/**
 * The ordering that actually breaks sites: a plugin that registers before Corex has booted must be
 * deferred, not dropped and not fatal.
 */
it('defers a callback registered before boot until the boot signal fires', function () {
    $ran = [];

    // Simulate the losing plugin: register against the signal while "not yet booted".
    add_action('corex_booted', static function () use (&$ran): void {
        $ran[] = 'deferred';
    });

    do_action('corex_booted', Boot::app());

    expect($ran)->toBe(['deferred']);

    remove_all_actions('corex_booted');
});
