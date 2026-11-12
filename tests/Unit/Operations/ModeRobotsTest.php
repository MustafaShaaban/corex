<?php

/**
 * Development and Staging sites keep search engines out (spec 124, FR-002 as amended 2026-11-12,
 * User Story 1 scenarios 3 to 5, DECISIONS #625).
 *
 * Contract: a site whose environment is development or staging carries `noindex` in the page's
 * robots meta and an `X-Robots-Tag: noindex` header in every mode, Coming soon, Forms paused and
 * Maintenance included, whether that environment is declared by the mode, locked (`APP_ENV`, the
 * network, the `corex_app_env` option) or inherited from WordPress's environment type. A
 * production site adds neither. The Reading setting is never written.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\ModeRobots;
use Corex\Config\Operations\OperationsMode;
use Corex\Environment\SiteEnvironment;
use Corex\Support\Config\Source;

/** A lock layer that answers `app.env` with the given value, or does not answer when it is null. */
function robotsLock(?string $value): Source
{
    return new class ($value) implements Source {
        public function __construct(private readonly ?string $value)
        {
        }

        public function has(string $key): bool
        {
            return $key === 'app.env' && $this->value !== null;
        }

        public function get(string $key): mixed
        {
            return $this->value;
        }
    };
}

/**
 * A robots class for a site whose stored mode is `$stored`, whose WordPress environment type is
 * `$environment`, and whose locks say what `$locks` says.
 *
 * @param array{dotenv?:string,network?:string,option?:string} $locks
 */
function modeRobots(string $stored, string $environment = 'production', array $locks = []): ModeRobots
{
    $GLOBALS['corex_test_options'] = ['corex_operations_mode' => $stored, 'blog_public' => '1'];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('wp_get_environment_type')->justReturn($environment);
    // The Reading setting is never written, by anything here.
    Functions\expect('update_option')->never();

    return new ModeRobots(new SiteEnvironment([
        [SiteEnvironment::BY_DOTENV, robotsLock($locks['dotenv'] ?? null)],
        [SiteEnvironment::BY_NETWORK, robotsLock($locks['network'] ?? null)],
        [SiteEnvironment::BY_OPTION, robotsLock($locks['option'] ?? null)],
    ]));
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

it('FR-002: adds neither the meta nor the header on an unlocked production site, in any mode', function (string $mode) {
    $robots = modeRobots($mode);

    expect($robots->keepsOut())->toBeFalse()
        ->and($robots->robots(['max-image-preview' => 'large']))->toBe(['max-image-preview' => 'large'])
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

it('FR-002 (amended): keeps a staging copy locked by APP_ENV out while it is in Maintenance', function () {
    $robots = modeRobots(OperationsMode::MAINTENANCE, 'production', ['dotenv' => 'staging']);

    expect($robots->keepsOut())->toBeTrue()
        ->and($robots->robots([]))->toBe(['noindex' => true])
        ->and($robots->headers([]))->toBe(['X-Robots-Tag' => 'noindex']);
});

it('FR-002 (amended): keeps a development copy locked by the network out while it is in Coming soon', function () {
    $robots = modeRobots(OperationsMode::COMING_SOON, 'production', ['network' => 'development']);

    expect($robots->robots([]))->toBe(['noindex' => true])
        ->and($robots->headers([]))->toBe(['X-Robots-Tag' => 'noindex']);
});

it('FR-002 (amended): keeps a staging copy locked by the corex_app_env option out in Forms paused', function () {
    expect(modeRobots(OperationsMode::FORMS_PAUSED, 'production', ['option' => 'staging'])->headers([]))
        ->toBe(['X-Robots-Tag' => 'noindex']);
});

it('FR-002 (amended): lets search engines into a site locked to production, whatever mode it stores', function () {
    $robots = modeRobots(OperationsMode::STAGING, 'staging', ['dotenv' => 'production']);

    expect($robots->keepsOut())->toBeFalse()
        ->and($robots->headers([]))->toBe([]);
});

it('FR-002: reads the environment on every response, so a change applies to the next page without a reload of CoreX', function () {
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
