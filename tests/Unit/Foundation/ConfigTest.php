<?php

/**
 * Unit tests for the layered configuration engine (spec US2: FR-011–FR-014, SC-003).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Support\BootLogger;
use Corex\Support\Config\Repository;
use Corex\Support\Config\Sources\DefaultsSource;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Support\Config\Sources\OptionsSource;

function tempEnvDir(?string $contents = null): string
{
    $dir = sys_get_temp_dir() . '/corex_env_' . uniqid('', true);
    mkdir($dir);

    if ($contents !== null) {
        file_put_contents($dir . '/.env', $contents);
    }

    return $dir;
}

function removeEnvDir(string $dir): void
{
    if (is_file($dir . '/.env')) {
        unlink($dir . '/.env');
    }

    if (is_dir($dir)) {
        rmdir($dir);
    }
}

it('reads nested defaults by dot key', function () {
    $source = new DefaultsSource(['app' => ['name' => 'Corex']]);

    expect($source->has('app.name'))->toBeTrue()
        ->and($source->get('app.name'))->toBe('Corex')
        ->and($source->has('app.missing'))->toBeFalse();
});

it('reads a WordPress option mapped from a dot key', function () {
    Functions\when('get_option')->alias(fn ($name, $default = false) => $name === 'corex_app_name' ? 'FromOption' : $default);

    $source = new OptionsSource();

    expect($source->has('app.name'))->toBeTrue()
        ->and($source->get('app.name'))->toBe('FromOption')
        ->and($source->has('app.other'))->toBeFalse();
});

it('reads an env var mapped from an uppercased dot key', function () {
    $dir = tempEnvDir("APP_NAME=FromEnv\n");

    $source = new DotenvSource($dir, new BootLogger(debug: false));

    expect($source->has('app.name'))->toBeTrue()
        ->and($source->get('app.name'))->toBe('FromEnv');

    removeEnvDir($dir);
});

it('treats an absent .env as empty without logging an error', function () {
    $dir = tempEnvDir();
    $logger = new BootLogger(debug: false);

    $source = new DotenvSource($dir, $logger);

    expect($source->has('app.name'))->toBeFalse()
        ->and($logger->messages())->toBeEmpty();

    removeEnvDir($dir);
});

it('logs and ignores a malformed .env instead of crashing boot', function () {
    $dir = tempEnvDir("FOO BAR=baz\n");
    $logger = new BootLogger(debug: false);

    $source = new DotenvSource($dir, $logger);

    expect($source->has('app.name'))->toBeFalse()
        ->and($logger->messages())->not->toBeEmpty();

    removeEnvDir($dir);
});

it('returns the default when only defaults hold the key', function () {
    $repository = new Repository([new DefaultsSource(['app' => ['name' => 'Corex']])]);

    expect($repository->get('app.name'))->toBe('Corex');
});

it('prefers a WordPress option over a shipped default', function () {
    Functions\when('get_option')->alias(fn ($name, $default = false) => $name === 'corex_app_name' ? 'FromOption' : $default);

    $repository = new Repository([
        new OptionsSource(),
        new DefaultsSource(['app' => ['name' => 'Corex']]),
    ]);

    expect($repository->get('app.name'))->toBe('FromOption');
});

it('prefers .env over both option and default', function () {
    Functions\when('get_option')->alias(fn ($name, $default = false) => $name === 'corex_app_name' ? 'FromOption' : $default);
    $dir = tempEnvDir("APP_NAME=FromEnv\n");

    $repository = new Repository([
        new DotenvSource($dir, new BootLogger(debug: false)),
        new OptionsSource(),
        new DefaultsSource(['app' => ['name' => 'Corex']]),
    ]);

    expect($repository->get('app.name'))->toBe('FromEnv');

    removeEnvDir($dir);
});

it('returns the caller fallback for a missing key', function () {
    $repository = new Repository([new DefaultsSource([])]);

    expect($repository->get('does.not.exist', 'fallback'))->toBe('fallback');
});
