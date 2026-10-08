<?php

/**
 * Unit test: which release a site says it is running, and whose (spec 107, plan D4).
 *
 * Which client a site is decides which packages it may be given, so "it does not know" has to
 * be an answer of its own and not an empty one that compares equal to something.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\InstalledRelease;

beforeEach(function () {
    $this->site = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
    mkdir($this->site);

    $this->recorded = static fn (mixed $option) => Functions\when('get_option')->justReturn($option);
    $this->left     = fn (string $json) => file_put_contents($this->site . '/corex-release.json', $json);
});

afterEach(function () {
    array_map('unlink', glob($this->site . '/*') ?: []);
    rmdir($this->site);
});

it('is what an installation from the admin recorded', function () {
    ($this->recorded)(['corex_version' => '0.44.0', 'client' => 'acme']);
    ($this->left)('{"corex_version":"0.40.0","client":"somebody-else"}');

    $installed = new InstalledRelease($this->site);

    expect($installed->isKnown())->toBeTrue()
        ->and($installed->version())->toBe('0.44.0')
        ->and($installed->client())->toBe('acme');
});

it('is what a package unpacked by hand left in the site, when nothing was recorded', function () {
    ($this->recorded)(false);
    // A package built before descriptions had a schema: still enough to say which client.
    ($this->left)('{"name":"corex-shared-host-dist","corex_version":"0.43.5","client":"acme"}');

    $installed = new InstalledRelease($this->site . '/');

    expect($installed->isKnown())->toBeTrue()
        ->and($installed->version())->toBe('0.43.5')
        ->and($installed->client())->toBe('acme');
});

it('knows it is the framework alone, which is not the same as not knowing', function () {
    ($this->recorded)(['corex_version' => '0.44.0', 'client' => null]);

    $installed = new InstalledRelease($this->site);

    expect($installed->isKnown())->toBeTrue()
        ->and($installed->client())->toBeNull();
});

it('does not know, on a site with neither', function (Closure $leave) {
    ($this->recorded)(false);
    $leave($this);

    $installed = new InstalledRelease($this->site);

    expect($installed->isKnown())->toBeFalse()
        ->and($installed->version())->toBeNull()
        ->and($installed->client())->toBeNull();
})->with([
    'nothing in its root'                => [fn () => null],
    'a file there that is not JSON'      => [fn ($test) => ($test->left)('<html>')],
    'a description that names no version' => [fn ($test) => ($test->left)('{"client":"acme"}')],
]);
