<?php

/**
 * The coming-soon page names no platform (spec 101; spec 111 FR-006), so it leaves out the CoreX
 * theme's favicon links too (spec 134, FR-022). The whole head is proved on real WordPress by
 * tests/Integration/Operations/ComingSoonHeadTest.php; this holds the theme's callback.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\ComingSoonHead;

it('leaves out the CoreX theme\'s favicon links, removed by name at their priority', function () {
    add_action('wp_head', 'corex_theme_favicon_links', 99);
    expect(has_action('wp_head', 'corex_theme_favicon_links'))->toBe(99);

    ComingSoonHead::quiet();

    expect(has_action('wp_head', 'corex_theme_favicon_links'))->toBeFalse();
});

it('is quiet under a theme that links no favicon', function () {
    ComingSoonHead::quiet();

    expect(has_action('wp_head', 'corex_theme_favicon_links'))->toBeFalse();
});
