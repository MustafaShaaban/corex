<?php

/**
 * Development and Staging keep search engines out (spec 124, FR-002, User Story 1 scenarios 3 to 5).
 *
 * Contract: in Development and Staging the page's robots meta says `noindex` and the response
 * carries `X-Robots-Tag: noindex`; every other mode adds neither; the Reading setting is never
 * written, so switching to Production needs no other step. The mode is the one in force, so a
 * mode inherited from WordPress's environment type counts as much as a declared one.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\ModeRobots;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

/** A robots class reading a site whose stored mode is `$stored` and whose environment type is `$environment`. */
function modeRobots(string $stored, string $environment = 'production'): ModeRobots
{
    $GLOBALS['corex_test_options'] = ['corex_operations_mode' => $stored, 'blog_public' => '1'];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('wp_get_environment_type')->justReturn($environment);
    // The Reading setting is never written, by anything here.
    Functions\expect('update_option')->never();

    return new ModeRobots(new OperationsModeStore(new OperationsMode()));
}

/** @return array<string,array{0:string}> */
function keptOutModes(): array
{
    return ['development' => [OperationsMode::DEVELOPMENT], 'staging' => [OperationsMode::STAGING]];
}

/** @return array<string,array{0:string}> */
function indexedModes(): array
{
    return [
        'production'   => [OperationsMode::PRODUCTION],
        'coming soon'  => [OperationsMode::COMING_SOON],
        'forms paused' => [OperationsMode::FORMS_PAUSED],
        'maintenance'  => [OperationsMode::MAINTENANCE],
    ];
}

it('FR-002: says noindex in the page in Development and Staging, keeping what was there', function (string $mode) {
    $robots = modeRobots($mode)->robots(['max-image-preview' => 'large']);

    expect($robots)->toBe(['max-image-preview' => 'large', 'noindex' => true]);
})->with(keptOutModes());

it('FR-002: sends X-Robots-Tag: noindex in Development and Staging', function (string $mode) {
    expect(modeRobots($mode)->headers(['Content-Type' => 'text/html']))
        ->toBe(['Content-Type' => 'text/html', 'X-Robots-Tag' => 'noindex']);
})->with(keptOutModes());

it('FR-002: adds noindex to an X-Robots-Tag another part of the site already set, and only once', function () {
    $robots = modeRobots(OperationsMode::STAGING);

    expect($robots->headers(['X-Robots-Tag' => 'noarchive'])['X-Robots-Tag'])->toBe('noarchive, noindex')
        ->and($robots->headers(['X-Robots-Tag' => 'noindex, nofollow'])['X-Robots-Tag'])->toBe('noindex, nofollow');
});

it('FR-002: adds neither the meta nor the header in any other mode', function (string $mode) {
    $robots = modeRobots($mode);

    expect($robots->robots(['max-image-preview' => 'large']))->toBe(['max-image-preview' => 'large'])
        ->and($robots->headers(['Content-Type' => 'text/html']))->toBe(['Content-Type' => 'text/html']);
})->with(indexedModes());

it('FR-002: leaves a noindex WordPress put there itself in every mode, so Settings > Reading still decides a live site', function (string $mode) {
    expect(modeRobots($mode)->robots(['noindex' => true, 'nofollow' => true]))->toMatchArray(['noindex' => true, 'nofollow' => true]);
})->with(indexedModes());

it('FR-002, scenario 5: keeps search engines out of a site that inherits Staging from WordPress', function () {
    $robots = modeRobots('', 'staging');

    expect($robots->robots([]))->toBe(['noindex' => true])
        ->and($robots->headers([]))->toBe(['X-Robots-Tag' => 'noindex']);
});

it('FR-002: keeps a local copy out, which WordPress calls local and CoreX calls Development', function () {
    expect(modeRobots('', 'local')->headers([]))->toBe(['X-Robots-Tag' => 'noindex']);
});

it('FR-002: reads the mode on every response, so a change applies to the next page without a reload of CoreX', function () {
    $robots = modeRobots(OperationsMode::DEVELOPMENT);
    expect($robots->headers([]))->toHaveKey('X-Robots-Tag');

    $GLOBALS['corex_test_options']['corex_operations_mode'] = OperationsMode::PRODUCTION;

    expect($robots->headers([]))->toBe([]);
});

it('FR-002: hooks the page robots and the front-end headers, and nothing in the admin or the REST API', function () {
    $robots = modeRobots(OperationsMode::DEVELOPMENT);
    Functions\expect('add_filter')->once()->with('wp_robots', [$robots, 'robots'], 20);
    Functions\expect('add_filter')->once()->with('wp_headers', [$robots, 'headers']);
    Functions\expect('add_action')->never();

    $robots->register();
});

it('FR-002: names the two modes that keep search engines out', function () {
    expect(ModeRobots::keepsOut(OperationsMode::DEVELOPMENT))->toBeTrue()
        ->and(ModeRobots::keepsOut(OperationsMode::STAGING))->toBeTrue()
        ->and(ModeRobots::keepsOut(OperationsMode::PRODUCTION))->toBeFalse()
        ->and(ModeRobots::keepsOut('anything else'))->toBeFalse();
});
