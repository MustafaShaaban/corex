<?php

/**
 * The Overview's state tiles (spec 118, US-OVR-04). No WordPress.
 *
 * Contract: four tiles, Site health, Environment, Login protection and Add-ons; a missing brand.json
 * is not a health problem (that is the facts' rule, held in the integration test); a recent fatal
 * error is said with its date, and its source only when it was given; the Environment tile names the
 * WordPress environment only when it differs; a fact that could not be read says "Could not check".
 *
 * @package Corex\Tests\Unit\Overview
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Overview\OverviewTiles;
use Corex\Config\Security\LoginProtection\LoginAddressState;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
    Functions\when('number_format_i18n')->alias(static fn ($number): string => (string) $number);
});

function tileFacts(array $overrides = []): array
{
    return array_merge([
        'health'      => ['issues' => [], 'fatal' => null],
        'environment' => ['label' => 'Production', 'detail' => 'Live environment.', 'wpType' => 'production', 'differs' => false],
        'login'       => LoginAddressState::HIDDEN,
        'addons'      => ['active' => 3, 'total' => 12],
    ], $overrides);
}

/** @return array<string,array<string,string>> */
function tilesByKey(array $overrides = []): array
{
    return array_column((new OverviewTiles())->build(tileFacts($overrides)), null, 'key');
}

it('draws four state tiles where the counts were', function () {
    $tiles = tilesByKey();

    expect(array_keys($tiles))->toBe(['health', 'environment', 'login', 'addons'])
        ->and($tiles['health']['value'])->toBe('Healthy')
        ->and($tiles['health']['meta'])->toBe('No PHP fatal errors in the last 7 days')
        ->and($tiles['health']['href'])->toBe('site-health.php')
        ->and($tiles['login']['value'])->toBe('On')
        ->and($tiles['addons']['value'])->toBe('3 of 12 active');
});

it('names what needs attention from the health checks', function () {
    $health = tilesByKey(['health' => ['issues' => ['The uploads directory is not writable.'], 'fatal' => null]])['health'];

    expect($health['value'])->toBe('Needs attention')
        ->and($health['detail'])->toBe('The uploads directory is not writable.');
});

it('says the date of the last fatal error, and where it came from only when it was given', function (string $source, string $meta) {
    $health = tilesByKey(['health' => ['issues' => [], 'fatal' => ['date' => 'October 22, 2026', 'source' => $source]]])['health'];

    expect($health['meta'])->toBe($meta)
        ->and($health['value'])->toBe('Needs attention');
})->with([
    'an administrator' => ['Hello Dolly', 'Last PHP fatal error: October 22, 2026, in Hello Dolly'],
    'anyone else' => ['', 'Last PHP fatal error: October 22, 2026'],
]);

it('names the WordPress environment beside the mode only when the two differ', function () {
    $differs = tilesByKey(['environment' => ['label' => 'Development', 'detail' => 'Not a live site.', 'wpType' => 'production', 'differs' => true]])['environment'];
    $same    = tilesByKey()['environment'];

    expect($differs['value'])->toBe('Development')
        ->and($differs['detail'])->toBe('WordPress environment: production')
        ->and($same['detail'])->toBe('Live environment.');
});

it('says "Could not check" on a tile whose fact could not be read, and the others still say theirs', function () {
    $tiles = tilesByKey(['health' => null, 'login' => null]);

    expect($tiles['health']['value'])->toBe('Could not check')
        ->and($tiles['login']['value'])->toBe('Could not check')
        ->and($tiles['addons']['value'])->toBe('3 of 12 active');
});
