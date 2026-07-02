<?php

/**
 * Unit tests for the pure Overview environment/mode resolver (spec 063, Phase 1). No WordPress.
 *
 * @package Corex\Tests\Unit\Overview
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Overview\EnvironmentMode;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('resolves production to a success badge', function () {
    $env = (new EnvironmentMode())->resolve('production');

    expect($env['mode'])->toBe('production')
        ->and($env['tone'])->toBe(EnvironmentMode::TONE_SUCCESS)
        ->and($env['label'])->not->toBe('')
        ->and($env['detail'])->not->toBe('');
});

it('resolves staging to a warning badge', function () {
    $env = (new EnvironmentMode())->resolve('staging');

    expect($env['mode'])->toBe('staging')
        ->and($env['tone'])->toBe(EnvironmentMode::TONE_WARNING);
});

it('resolves development and local to an info badge', function () {
    $dev   = (new EnvironmentMode())->resolve('development');
    $local = (new EnvironmentMode())->resolve('local');

    expect($dev['mode'])->toBe('development')
        ->and($dev['tone'])->toBe(EnvironmentMode::TONE_INFO)
        ->and($local['mode'])->toBe('local')
        ->and($local['tone'])->toBe(EnvironmentMode::TONE_INFO);
});

it('mirrors WordPress by defaulting unknown or empty types to production, never an invented mode', function () {
    $unknown = (new EnvironmentMode())->resolve('banana');
    $empty   = (new EnvironmentMode())->resolve('');

    expect($unknown['mode'])->toBe('production')
        ->and($unknown['tone'])->toBe(EnvironmentMode::TONE_SUCCESS)
        ->and($empty['mode'])->toBe('production');
});

it('is case-insensitive to the environment type', function () {
    expect((new EnvironmentMode())->resolve('STAGING')['mode'])->toBe('staging');
});
