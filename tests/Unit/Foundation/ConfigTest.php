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

require_once dirname(__DIR__) . '/Config/OptionFixtures.php';

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
    siteWithOptions(['corex_app_name' => 'FromOption']);

    $source = new OptionsSource();

    expect($source->has('app.name'))->toBeTrue()
        ->and($source->get('app.name'))->toBe('FromOption');
});

it('holds a stored option that is empty, which is a value and not an absence', function () {
    siteWithOptions(['corex_app_name' => '']);

    $source = new OptionsSource();

    expect($source->has('app.name'))->toBeTrue()
        ->and($source->get('app.name'))->toBe('');
});

it('does not ask WordPress for an option that is not stored', function () {
    Functions\when('wp_installing')->justReturn(false);
    Functions\when('wp_load_alloptions')->justReturn(['corex_app_name' => 'FromOption']);
    // On a site with no persistent object cache, asking is a query on every request.
    Functions\expect('get_option')->never();

    expect((new OptionsSource())->has('app.other'))->toBeFalse();
});

it('asks WordPress for an option that is not stored when a filter may answer for it', function (string $hook) {
    siteWithOptions([]);
    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => $name === 'corex_app_name' ? 'FromFilter' : $default,
    );
    add_filter($hook, '__return_empty_string');

    $source = new OptionsSource();

    expect($source->has('app.name'))->toBeTrue()
        ->and($source->get('app.name'))->toBe('FromFilter');
})->with([
    'a short-circuit of this option' => ['pre_option_corex_app_name'],
    'the short-circuit of every option' => ['pre_option'],
    'a default for this option' => ['default_option_corex_app_name'],
]);

it('asks WordPress for every option while a site is being installed', function () {
    siteWithOptions([]);
    Functions\when('wp_installing')->justReturn(true);
    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => $name === 'corex_app_name' ? 'JustWritten' : $default,
    );

    $source = new OptionsSource();

    expect($source->has('app.name'))->toBeTrue()
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
    siteWithOptions(['corex_app_name' => 'FromOption']);

    $repository = new Repository([
        new OptionsSource(),
        new DefaultsSource(['app' => ['name' => 'Corex']]),
    ]);

    expect($repository->get('app.name'))->toBe('FromOption');
});

it('prefers .env over both option and default', function () {
    siteWithOptions(['corex_app_name' => 'FromOption']);
    $dir = tempEnvDir("APP_NAME=FromEnv\n");

    $repository = new Repository([
        new DotenvSource($dir, new BootLogger(debug: false)),
        new OptionsSource(),
        new DefaultsSource(['app' => ['name' => 'Corex']]),
    ]);

    expect($repository->get('app.name'))->toBe('FromEnv');

    removeEnvDir($dir);
});

it('falls through to .env and then to the shipped default when no option is stored', function () {
    siteWithOptions([]);
    $dir = tempEnvDir("APP_NAME=FromEnv
");

    $repository = new Repository([
        new DotenvSource($dir, new BootLogger(debug: false)),
        new OptionsSource(),
        new DefaultsSource(['app' => ['name' => 'Corex', 'env' => 'production']]),
    ]);

    expect($repository->get('app.name'))->toBe('FromEnv')
        ->and($repository->get('app.env'))->toBe('production')
        ->and($repository->get('app.missing', 'fallback'))->toBe('fallback');

    removeEnvDir($dir);
});

it('returns the caller fallback for a missing key', function () {
    $repository = new Repository([new DefaultsSource([])]);

    expect($repository->get('does.not.exist', 'fallback'))->toBe('fallback');
});
