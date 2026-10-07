<?php

/**
 * Where what WordPress prints before the page goes on a CoreX admin screen.
 *
 * The browser proves the outcome: no band above the shell, and the update nag under the page
 * header (`tests/e2e/admin-core-notices.spec.js`). These prove what a browser cannot be made to
 * show: that output is held back only on a CoreX screen, that a buffer somebody else left open is
 * not closed, and that markup no shell asked for is still printed.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\AdminUi\ScreenNotices;

/**
 * Run a callback and answer with what it sent to the output.
 */
function outputOf(callable $callback): string
{
    ob_start();
    $callback();

    return (string) ob_get_clean();
}

function onScreen(?string $id): void
{
    Functions\when('get_current_screen')->justReturn($id === null ? null : (object) ['id' => $id]);
}

afterEach(function () {
    unset($GLOBALS['page_hook']);
});

it('opens before every notice and closes after the last one', function () {
    $notices = new ScreenNotices();
    $notices->register();

    // `update_nag` is hooked at 3 and `all_admin_notices` fires after `admin_notices`, so these
    // two priorities are what put every notice between them.
    expect(has_action('admin_notices', [$notices, 'begin']))->toBe(PHP_INT_MIN)
        ->and(has_action('all_admin_notices', [$notices, 'end']))->toBe(PHP_INT_MAX)
        ->and(has_filter('corex_admin_notices', [$notices, 'place']))->toBe(10);
});

it('holds back what is printed on a CoreX screen and hands it to the shell once', function () {
    onScreen('corex_page_corex-forms');
    $notices = new ScreenNotices();

    $printed = outputOf(function () use ($notices) {
        $notices->begin();
        echo '<div class="update-nag">WordPress 7.1.3 is available!</div>';
        $notices->end();
    });

    expect($printed)->toBe('')
        ->and($notices->place(''))->toBe('<div class="update-nag">WordPress 7.1.3 is available!</div>')
        // A second shell in the same request must not print the notice again.
        ->and($notices->place(''))->toBe('');
});

it('adds to what an earlier contributor put in the region', function () {
    onScreen('toplevel_page_corex-settings');
    $notices = new ScreenNotices();

    outputOf(function () use ($notices) {
        $notices->begin();
        echo '<div class="notice">Second.</div>';
        $notices->end();
    });

    expect($notices->place('<div class="notice">First.</div>'))
        ->toBe('<div class="notice">First.</div><div class="notice">Second.</div>');
});

it('leaves the output of every other admin screen where WordPress printed it', function (?string $screen) {
    onScreen($screen);
    $notices = new ScreenNotices();

    $printed = outputOf(function () use ($notices) {
        $notices->begin();
        echo '<div class="update-nag">nag</div>';
        $notices->end();
    });

    expect($printed)->toBe('<div class="update-nag">nag</div>')
        ->and($notices->place(''))->toBe('');
})->with([
    'the dashboard' => ['dashboard'],
    'the plugins screen' => ['plugins'],
    'a request with no screen' => [null],
]);

it('does not close a buffer a notice callback left open', function () {
    onScreen('corex_page_corex-forms');
    $notices = new ScreenNotices();

    $printed = outputOf(function () use ($notices) {
        $notices->begin();
        echo 'before';
        ob_start();
        echo 'inside';
        $notices->end();

        // The callback's buffer is still the one on top, with its content in it. Closing it from
        // `end()` would have taken output that is not a notice's.
        expect(ob_get_contents())->toBe('inside');

        ob_end_flush();
        ob_end_flush();
    });

    // Nothing was lost: it all reaches the page, where WordPress printed it.
    expect($printed)->toBe('beforeinside')
        ->and($notices->place(''))->toBe('');
});

it('prints what no shell asked for when the page callback has finished', function () {
    onScreen('corex_page_corex-foreign');
    $GLOBALS['page_hook'] = 'corex_page_corex-foreign';
    $notices = new ScreenNotices();

    outputOf(function () use ($notices) {
        $notices->begin();
        echo '<div class="update-nag">nag</div>';
        $notices->end();
    });

    // After the page's own callback, on the hook WordPress calls the page through.
    expect(has_action('corex_page_corex-foreign', [$notices, 'printUnplaced']))->toBe(PHP_INT_MAX)
        ->and(outputOf([$notices, 'printUnplaced']))->toBe('<div class="update-nag">nag</div>');
});

it('prints nothing after the page when the shell has already placed the notices', function () {
    onScreen('corex_page_corex-forms');
    $GLOBALS['page_hook'] = 'corex_page_corex-forms';
    $notices = new ScreenNotices();

    outputOf(function () use ($notices) {
        $notices->begin();
        echo '<div class="update-nag">nag</div>';
        $notices->end();
    });
    $notices->place('');

    expect(outputOf([$notices, 'printUnplaced']))->toBe('');
});
