<?php

/**
 * Integration test: CoreX's generator tag in a real `wp_head`, on real ./wp (spec 111).
 *
 * WordPress's own head runs, so the mark is read the way a scanner reads it: among everything
 * else core and the active plugins print.
 *
 * @package Corex\Tests\Integration\Theme
 */

declare(strict_types=1);

use Corex\Theme\GeneratorTag;

const COREX_MARK = '/<meta name="generator" content="CoreX" \/>/';

function headWithMarks(): string
{
    ob_start();
    do_action('wp_head');

    return (string) ob_get_clean();
}

beforeEach(function () {
    $hooks = $GLOBALS['wp_filter']['wp_head'] ?? null;
    $this->head = $hooks === null ? null : clone $hooks;
    // WordPress's resource hints compare each queued asset's host with it, and a command-line run
    // has none: give the head the server name a request to the site would carry.
    $this->serverName = $_SERVER['SERVER_NAME'] ?? null;
    $_SERVER['SERVER_NAME'] = (string) wp_parse_url(home_url(), PHP_URL_HOST);
});

afterEach(function () {
    // Every later test in this process shares WordPress's hooks.
    $GLOBALS['wp_filter']['wp_head'] = $this->head;
    remove_all_filters(GeneratorTag::FILTER);
    if ($this->serverName === null) {
        unset($_SERVER['SERVER_NAME']);
    } else {
        $_SERVER['SERVER_NAME'] = $this->serverName;
    }
});

it('is printed once in the page head, beside WordPress\'s own', function () {
    $head = headWithMarks();

    expect(preg_match_all(COREX_MARK, $head))->toBe(1)
        ->and($head)->toMatch('/<meta name="generator" content="WordPress [\d.]+/');
});

it('stays when the site removes WordPress\'s tag', function () {
    remove_action('wp_head', 'wp_generator');

    $head = headWithMarks();

    expect(preg_match_all(COREX_MARK, $head))->toBe(1)
        ->and($head)->not->toContain('content="WordPress');
});

it('goes from every page when the site answers the filter with false, and comes back without it', function () {
    add_filter(GeneratorTag::FILTER, '__return_false');
    expect(preg_match(COREX_MARK, headWithMarks()))->toBe(0);

    remove_filter(GeneratorTag::FILTER, '__return_false');
    expect(preg_match(COREX_MARK, headWithMarks()))->toBe(1);
});

it('is hooked on the front end\'s head and on no other', function () {
    // Admin screens, the login page, embeds and feeds each print a head of their own, through
    // their own hook; firing those here would run admin-only callbacks outside the admin.
    $printing = [];
    foreach ($GLOBALS['wp_filter'] as $hook => $callbacks) {
        foreach ($callbacks->callbacks as $byPriority) {
            foreach ($byPriority as $callback) {
                $function = $callback['function'];
                if (is_array($function) && $function[0] instanceof GeneratorTag && $function[1] === 'print') {
                    $printing[] = $hook;
                }
            }
        }
    }

    expect($printing)->toBe(['wp_head']);
});
