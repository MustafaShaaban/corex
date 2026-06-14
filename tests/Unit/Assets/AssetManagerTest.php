<?php

/**
 * Unit tests for the asset manager (spec 047: US1/US3). Plain string + filesystem work —
 * no WordPress — using a real temp file for filemtime.
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Corex\Assets\AssetEnvironment;
use Corex\Assets\AssetManager;
use Corex\Assets\AssetVersion;
use Corex\Assets\BuildManifest;

function assetBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_assets_' . uniqid('', true);
    mkdir($dir . '/build', 0777, true);
    file_put_contents($dir . '/build/app.css', 'body{}');

    return $dir;
}

function assetManager(string $base, AssetEnvironment $env, BuildManifest $manifest): AssetManager
{
    return new AssetManager($base, 'https://example.test/app', $env, $manifest, '0.25.0', new AssetVersion());
}

it('builds the url and path for a plain asset', function () {
    $base = assetBase();
    $m    = assetManager($base, AssetEnvironment::from('local'), BuildManifest::fromArray([]));

    expect($m->url('build/app.css'))->toBe('https://example.test/app/build/app.css')
        ->and($m->path('build/app.css'))->toBe($base . DIRECTORY_SEPARATOR . 'build/app.css');
});

it('versions a local asset by its filemtime', function () {
    $base  = assetBase();
    $m     = assetManager($base, AssetEnvironment::from('local'), BuildManifest::fromArray([]));
    $mtime = (string) filemtime($base . '/build/app.css');

    expect($m->version('build/app.css'))->toBe($mtime);
});

it('resolves a manifest entry to its hashed file + hash in production', function () {
    $base = assetBase();
    $manifest = BuildManifest::fromArray(['build/app.css' => ['file' => 'build/app.4f3a.css', 'hash' => '4f3a']]);
    $m = assetManager($base, AssetEnvironment::from('production'), $manifest);

    expect($m->url('build/app.css'))->toBe('https://example.test/app/build/app.4f3a.css')
        ->and($m->version('build/app.css'))->toBe('4f3a');
});

it('falls back to the framework version for a missing production asset', function () {
    $base = assetBase();
    $m    = assetManager($base, AssetEnvironment::from('production'), BuildManifest::fromArray([]));

    expect($m->version('build/missing.css'))->toBe('0.25.0');
});
