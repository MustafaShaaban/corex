<?php

/**
 * Unit tests for the per-environment asset version strategy (spec 047: US2, FR-003/FR-005).
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Corex\Assets\AssetEnvironment;
use Corex\Assets\AssetVersion;

beforeEach(function () {
    $this->version = new AssetVersion();
    $this->local   = AssetEnvironment::from('local');
    $this->prod    = AssetEnvironment::from('production');
});

it('uses filemtime in local', function () {
    expect($this->version->token('build/app.css', 1234567, 'abc123', $this->local, '0.25.0'))->toBe('1234567');
});

it('uses the manifest hash in production', function () {
    expect($this->version->token('build/app.css', 1234567, 'abc123', $this->prod, '0.25.0'))->toBe('abc123');
});

it('falls back to the framework/site version in production without a manifest hash', function () {
    expect($this->version->token('build/app.css', 1234567, null, $this->prod, '0.25.0'))->toBe('0.25.0')
        ->and($this->version->token('build/app.css', 1234567, '', $this->prod, '0.25.0'))->toBe('0.25.0');
});

it('falls back when the asset is missing (no mtime, no hash)', function () {
    expect($this->version->token('build/app.css', null, null, $this->local, '0.25.0'))->toBe('0.25.0');
});

it('refuses a path that escapes its base, falling back safely', function () {
    expect($this->version->token('../../wp-config.php', 1234567, 'abc123', $this->local, '0.25.0'))->toBe('0.25.0');
});
