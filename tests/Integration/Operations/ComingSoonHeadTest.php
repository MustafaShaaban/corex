<?php

/**
 * Integration test: what the coming-soon page leaves out of its head, on real ./wp.
 *
 * Reported from the first site that used the mode in public: the page that says "coming soon"
 * also printed WordPress's generator tag, the RSD link, feed links and the emoji loader.
 *
 * WordPress's own `wp_head` is run, before and after, so a core release that renames one of
 * these callbacks or moves its priority fails here.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\ComingSoonHead;

const HEAD_DISCLOSURES = [
    'the generator tag'  => '/<meta[^>]+name=["\']generator["\']/',
    'the RSD link'       => '/rel=["\']EditURI["\']/',
    'a feed link'        => '/type=["\']application\/rss\+xml["\']/',
];

function printedHead(): string
{
    ob_start();
    do_action('wp_head');

    return (string) ob_get_clean();
}

beforeEach(function () {
    // A block theme does not always declare feed links; the page must not print them when one does.
    add_theme_support('automatic-feed-links');
    $this->before = $GLOBALS['wp_filter']['wp_head'] ?? null;
    $this->beforeEnqueue = $GLOBALS['wp_filter']['wp_enqueue_scripts'] ?? null;
    $this->beforePrint = $GLOBALS['wp_filter']['wp_print_styles'] ?? null;
    $this->before = $this->before === null ? null : clone $this->before;
    $this->beforeEnqueue = $this->beforeEnqueue === null ? null : clone $this->beforeEnqueue;
    $this->beforePrint = $this->beforePrint === null ? null : clone $this->beforePrint;
});

afterEach(function () {
    // Put WordPress's hooks back as they were: every later test in this process shares them.
    $GLOBALS['wp_filter']['wp_head'] = $this->before;
    $GLOBALS['wp_filter']['wp_enqueue_scripts'] = $this->beforeEnqueue;
    $GLOBALS['wp_filter']['wp_print_styles'] = $this->beforePrint;
});

it('prints each of them on an ordinary page, which is what makes the next test mean something', function () {
    $head = printedHead();

    foreach (HEAD_DISCLOSURES as $what => $pattern) {
        expect(preg_match($pattern, $head))->toBe(1, 'WordPress no longer prints ' . $what . ' by default');
    }
    // The emoji loader is queued from the head and printed with the footer's scripts, once per
    // process, so it is read off the hook it is queued on.
    expect(has_action('wp_head', 'print_emoji_detection_script'))->toBe(7);
});

it('prints none of them on the coming-soon page', function () {
    ComingSoonHead::quiet();
    $head = printedHead();

    foreach (HEAD_DISCLOSURES as $what => $pattern) {
        expect(preg_match($pattern, $head))->toBe(0, 'the page still prints ' . $what);
    }
    expect(has_action('wp_head', 'print_emoji_detection_script'))->toBeFalse()
        ->and(has_action('wp_enqueue_scripts', 'wp_enqueue_emoji_styles'))->toBeFalse();
});
