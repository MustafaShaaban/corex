<?php

/**
 * Unit tests for the build manifest reader (spec 047: US3, FR-008/FR-009).
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Corex\Assets\BuildManifest;

it('looks up a structured entry to a hashed file + hash', function () {
    $manifest = BuildManifest::fromArray([
        'app.css' => ['file' => 'app.4f3a.css', 'hash' => '4f3a'],
    ]);

    expect($manifest->lookup('app.css'))->toBe(['file' => 'app.4f3a.css', 'hash' => '4f3a']);
});

it('looks up a plain string entry to a file with no hash', function () {
    $manifest = BuildManifest::fromArray(['app.js' => 'app.9b2c.js']);

    expect($manifest->lookup('app.js'))->toBe(['file' => 'app.9b2c.js', 'hash' => '']);
});

it('returns null for a missing entry and is empty for malformed/absent data', function () {
    expect(BuildManifest::fromArray(['x' => 'y'])->lookup('nope'))->toBeNull()
        ->and(BuildManifest::fromArray(null)->isEmpty())->toBeTrue()
        ->and(BuildManifest::fromArray('garbage')->isEmpty())->toBeTrue()
        ->and(BuildManifest::fromArray([])->lookup('x'))->toBeNull();
});
