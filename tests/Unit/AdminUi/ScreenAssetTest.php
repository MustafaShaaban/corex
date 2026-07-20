<?php

/**
 * Unit tests for the admin screen asset cache-busting version (spec 069, FR-029). No WordPress.
 * Contract: an edit to a source stylesheet must change its enqueued version, or every CSS fix
 * ships stale to anyone who has already loaded the screen.
 *
 * @package Corex\Tests\Unit\AdminUi
 */

declare(strict_types=1);

use Corex\Config\AdminUi\ScreenAsset;

beforeEach(function () {
    $this->file = sys_get_temp_dir() . '/corex-screen-asset-' . uniqid() . '.css';
    file_put_contents($this->file, '.corex-admin {}');
});

afterEach(function () {
    if (is_file($this->file)) {
        unlink($this->file);
    }
});

it('versions a real asset by its modification time', function () {
    expect(ScreenAsset::version($this->file))->toBe((string) filemtime($this->file));
});

it('changes the version when the asset is edited', function () {
    $before = ScreenAsset::version($this->file);

    // Touch a second into the future rather than sleeping: filemtime has one-second resolution,
    // so an immediate rewrite would land in the same second and the assertion would pass or fail
    // on timing rather than on behaviour.
    touch($this->file, time() + 1);
    clearstatcache(true, $this->file);

    expect(ScreenAsset::version($this->file))->not->toBe($before);
});

it('never returns a version that cannot bust — the defect this replaces', function () {
    // Insights sat on a hardcoded '1.1.0' through every restyle, so returning visitors kept the
    // old sheet and each fix looked like it had not landed. Whatever we return must be derived
    // from the file, not from a literal someone has to remember to bump.
    expect(ScreenAsset::version($this->file))->not->toBe('1.1.0');
});

it('falls back to the plugin version instead of failing on a missing asset', function () {
    // A stylesheet that is not there must not fatal an admin screen.
    expect(ScreenAsset::version($this->file . '.does-not-exist'))
        ->toBe(defined('COREX_CONFIG_VERSION') ? (string) COREX_CONFIG_VERSION : '0.0.0');
});
