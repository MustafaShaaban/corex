<?php

/**
 * Development and staging sites keep search engines out, on real WordPress (spec 124, FR-002 as
 * amended 2026-11-12, User Story 1 scenarios 3 and 4, DECISIONS #625).
 *
 * Each mode is answered by a `pre_option_` filter, as the other tests of this folder do, so the
 * stored mode of the install this runs against is never written. Through that filter the page's
 * robots meta is what `wp_robots()` prints, and the header is what WordPress's front-end pipeline
 * sends (`wp_headers`, applied by `WP::send_headers()`). One test then asks the running site over
 * HTTP for its home page and its feed, in the mode it is really in, and reads both.
 *
 * A lock is stood in for the same way: the `corex_app_env` option, the lowest of the three, is
 * answered by `pre_option_corex_app_env`, and nothing is stored.
 *
 * Scenario 5 (a mode inherited from WordPress's environment type) needs the environment type
 * changed, which WordPress fixes for the whole process, so it is proved by the unit test.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Operations\ComingSoonTemplate;
use Corex\Config\Operations\ModeRobots;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

/** The robots meta the page carries. */
function corexRobotsMeta(): string
{
    ob_start();
    wp_robots();

    return (string) ob_get_clean();
}

/** The headers WordPress's front-end pipeline would send. */
function corexFrontEndHeaders(): array
{
    return (array) apply_filters('wp_headers', [], $GLOBALS['wp']);
}

/** @return array<string,array{0:string,1:bool}> each mode, and whether it keeps search engines out */
function corexModesAndIndexing(): array
{
    return [
        'development'  => [OperationsMode::DEVELOPMENT, true],
        'staging'      => [OperationsMode::STAGING, true],
        'production'   => [OperationsMode::PRODUCTION, false],
        'coming soon'  => [OperationsMode::COMING_SOON, false],
        'forms paused' => [OperationsMode::FORMS_PAUSED, false],
        'maintenance'  => [OperationsMode::MAINTENANCE, false],
    ];
}

beforeEach(function () {
    $this->mode = OperationsMode::DEVELOPMENT;
    $this->answerMode = fn (): string => $this->mode;
    add_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);

    $this->readingWas = get_option('blog_public');
    $this->readingWrites = 0;
    $this->countReadingWrite = function ($value) {
        $this->readingWrites++;

        return $value;
    };
    add_filter('pre_update_option_blog_public', $this->countReadingWrite);
});

afterEach(function () {
    remove_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);
    remove_filter('pre_update_option_blog_public', $this->countReadingWrite);
});

it('FR-002: is registered on the page robots and the front-end headers', function () {
    $robots = Boot::app()->container()->make(ModeRobots::class);

    expect(has_filter('wp_robots', [$robots, 'robots']))->toBe(20)
        ->and(has_filter('wp_headers', [$robots, 'headers']))->not->toBeFalse();
});

it('FR-002: says noindex in the page and the header in Development and Staging only', function (string $mode, bool $keptOut) {
    // The install this runs against lets search engines in, so any noindex here is the mode's.
    expect($this->readingWas)->toBe('1');
    $this->mode = $mode;

    $meta    = corexRobotsMeta();
    $headers = corexFrontEndHeaders();

    if ($keptOut) {
        expect($meta)->toMatch("/<meta name='robots' content='[^']*\\bnoindex\\b/")
            ->and($headers['X-Robots-Tag'] ?? '')->toBe('noindex');
    } else {
        expect($meta)->not->toContain('noindex')
            ->and($headers)->not->toHaveKey('X-Robots-Tag');
    }

    expect($this->readingWrites)->toBe(0)
        ->and(get_option('blog_public'))->toBe($this->readingWas);
})->with(corexModesAndIndexing());

it('FR-002, scenario 4: takes the noindex away when Development becomes Production, and the Reading setting is what it was', function () {
    $this->mode = OperationsMode::DEVELOPMENT;
    expect(corexRobotsMeta())->toContain('noindex')
        ->and(corexFrontEndHeaders())->toHaveKey('X-Robots-Tag');

    $this->mode = OperationsMode::PRODUCTION;

    expect(corexRobotsMeta())->not->toContain('noindex')
        ->and(corexFrontEndHeaders())->not->toHaveKey('X-Robots-Tag')
        ->and($this->readingWrites)->toBe(0)
        ->and(get_option('blog_public'))->toBe($this->readingWas);
});

it('FR-002, scenario 3: answers a signed-out visitor\'s page and feed with noindex, over HTTP, in the mode the site is in', function (string $path) {
    remove_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);
    if (! Boot::app()->container()->make(ModeRobots::class)->keepsOut()) {
        $mode = Boot::app()->container()->make(OperationsModeStore::class)->current();
        $this->markTestSkipped("This site is a production site, in {$mode}; the per-mode test above covers what adds nothing.");
    }

    $response = wp_remote_get(home_url($path), ['timeout' => 30, 'sslverify' => false]);
    expect($response)->not->toBeInstanceOf(WP_Error::class);

    expect(wp_remote_retrieve_response_code($response))->toBe(200)
        ->and((string) wp_remote_retrieve_header($response, 'x-robots-tag'))->toContain('noindex');

    if ($path === '/') {
        expect(wp_remote_retrieve_body($response))->toMatch("/<meta name='robots' content='[^']*\\bnoindex\\b/");
    }
})->with(['home page' => '/', 'feed' => '/feed/']);

it('FR-002 (amended): keeps a staging copy locked by the corex_app_env option out in every mode, visitor modes included', function (string $mode) {
    $this->mode = $mode;
    $lock = static fn (): string => 'staging';
    add_filter('pre_option_corex_app_env', $lock);

    $meta    = corexRobotsMeta();
    $headers = corexFrontEndHeaders();
    $page    = Boot::app()->container()->make(ComingSoonTemplate::class)->standalone();
    remove_filter('pre_option_corex_app_env', $lock);

    expect($meta)->toMatch("/<meta name='robots' content='[^']*\bnoindex\b/")
        ->and($headers['X-Robots-Tag'] ?? '')->toBe('noindex')
        // The Coming soon page of a theme with no block templates never runs wp_head.
        ->and($page)->toContain('<meta name="robots" content="noindex, nofollow"')
        ->and($this->readingWrites)->toBe(0);
})->with([
    'production'   => [OperationsMode::PRODUCTION],
    'coming soon'  => [OperationsMode::COMING_SOON],
    'forms paused' => [OperationsMode::FORMS_PAUSED],
    'maintenance'  => [OperationsMode::MAINTENANCE],
]);