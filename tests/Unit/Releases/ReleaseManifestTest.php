<?php

/**
 * Unit test: a release package's description of itself, read on a site (spec 107, plan D2, D3).
 *
 * A package that cannot be read for what it is must be refused for a reason an administrator can
 * act on, before anything of it is unpacked.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseManifest;
use Corex\Config\Releases\ReleaseRefused;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/**
 * A description as the builder writes it, with some of it changed.
 *
 * @param array<string,mixed> $changes Keys to replace; a null value removes the key.
 */
function releaseDescription(array $changes = []): string
{
    $description = array_merge([
        'name'              => 'corex-shared-host-dist',
        'schema'            => 2,
        'built_at'          => '2026-10-08T18:00:00.000Z',
        'corex_version'     => '0.44.0',
        'client'            => 'acme',
        'plugins'           => ['acme-site', 'corex-core'],
        'themes'            => ['corex'],
        'requires'          => ['php' => '8.3', 'wordpress' => '7.0'],
        'wordpress_version' => '7.1.3',
        'release_paths'     => ['wp-content/plugins/acme-site', 'wp-content/plugins/corex-core', 'wp-content/themes/corex', 'wp-content/vendor'],
        'contents'          => [
            'wp-content/plugins/acme-site'  => ['files' => 2, 'bytes' => 40, 'hash' => str_repeat('a', 64)],
            'wp-content/plugins/corex-core' => ['files' => 9, 'bytes' => 900, 'hash' => str_repeat('b', 64)],
            'wp-content/themes/corex'       => ['files' => 4, 'bytes' => 400, 'hash' => str_repeat('c', 64)],
            'wp-content/vendor'             => ['files' => 30, 'bytes' => 3000, 'hash' => str_repeat('d', 64)],
        ],
        'autoload' => ['file' => 'wp-content/vendor/autoload.php', 'psr4' => ['Corex\\' => 'wp-content/plugins/corex-core/src/']],
    ], $changes);

    return (string) json_encode(array_filter($description, static fn (mixed $value): bool => $value !== null));
}

it('reads what a package says it is', function () {
    $manifest = ReleaseManifest::fromJson(releaseDescription());

    expect($manifest->version)->toBe('0.44.0')
        ->and($manifest->client)->toBe('acme')
        ->and($manifest->builtAt->format('Y-m-d H:i'))->toBe('2026-10-08 18:00')
        ->and($manifest->requiresPhp)->toBe('8.3')
        ->and($manifest->requiresWordPress)->toBe('7.0')
        ->and($manifest->wordPressVersion)->toBe('7.1.3')
        ->and($manifest->releasePaths)->toHaveCount(4)
        ->and($manifest->contentsOf('wp-content/vendor'))->toBe(['files' => 30, 'bytes' => 3000, 'hash' => str_repeat('d', 64)])
        ->and($manifest->autoloadFile)->toBe('wp-content/vendor/autoload.php')
        ->and($manifest->namespaceFolders)->toBe(['wp-content/plugins/corex-core/src/']);
});

it('reads a package of the framework alone, built with no WordPress beside it', function () {
    $manifest = ReleaseManifest::fromJson(releaseDescription(['client' => null, 'wordpress_version' => null]));

    expect($manifest->client)->toBeNull()
        ->and($manifest->wordPressVersion)->toBeNull();
});

it('refuses a description it cannot rely on, and says why', function (string $json, string $reason) {
    expect(fn () => ReleaseManifest::fromJson($json))
        ->toThrow(function (ReleaseRefused $refused) use ($reason) {
            expect($refused->reason)->toBe($reason);
        });
})->with([
    'not JSON'                                   => ['<html>Just a moment…</html>', ReleaseRefused::NOT_A_PACKAGE],
    'JSON that is something else'                => ['{"name":"some-other-zip"}', ReleaseRefused::NOT_A_PACKAGE],
    'a package built before packages said this'  => [fn () => releaseDescription(['schema' => null]), ReleaseRefused::BUILT_BEFORE],
    'a schema newer than this site understands'  => [fn () => releaseDescription(['schema' => 3]), ReleaseRefused::BUILT_AFTER],
    'no version'                                 => [fn () => releaseDescription(['corex_version' => 'unknown']), ReleaseRefused::INCOMPLETE],
    'no requirements'                            => [fn () => releaseDescription(['requires' => null]), ReleaseRefused::INCOMPLETE],
    'nothing to install'                         => [fn () => releaseDescription(['release_paths' => []]), ReleaseRefused::INCOMPLETE],
    'a folder it names and does not measure'     => [
        fn () => releaseDescription(['release_paths' => ['wp-content/plugins/corex-core', 'wp-content/plugins/extra']]),
        ReleaseRefused::INCOMPLETE,
    ],
    'a hash that is not one'                     => [
        fn () => releaseDescription(['release_paths' => ['wp-content/vendor'], 'contents' => ['wp-content/vendor' => ['files' => 1, 'bytes' => 1, 'hash' => 'nope']]]),
        ReleaseRefused::INCOMPLETE,
    ],
]);

it('refuses a folder that would be written outside what a release owns', function (string $path) {
    $json = releaseDescription([
        'release_paths' => [$path],
        'contents'      => [$path => ['files' => 1, 'bytes' => 1, 'hash' => str_repeat('e', 64)]],
    ]);

    expect(fn () => ReleaseManifest::fromJson($json))
        ->toThrow(function (ReleaseRefused $refused) {
            expect($refused->reason)->toBe(ReleaseRefused::UNSAFE_PATH);
        });
})->with([
    'up and out'                 => ['wp-content/plugins/../../wp-config.php'],
    'an absolute path'           => ['/etc/cron.d'],
    'a drive'                    => ['C:/Windows'],
    'a backslash'                => ['wp-content\\plugins\\corex-core'],
    'WordPress itself'           => ['wp-includes'],
    'uploads'                    => ['wp-content/uploads'],
    'every plugin at once'       => ['wp-content/plugins'],
    'the must-use plugins'       => ['wp-content/mu-plugins'],
    'the installer\'s own place' => ['wp-content/corex-releases'],
    'a folder inside a plugin'   => ['wp-content/plugins/corex-core/src'],
    'an empty segment'           => ['wp-content//vendor'],
]);
