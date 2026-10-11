<?php

/**
 * Unit test: what stops a host installing a release, said before anybody tries (spec 107,
 * FR-017; plan D15).
 *
 * The facts are read from the host in one place and judged in another. This is the judging:
 * each fact that prevents an installation named for what it is, with what to do about it.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseHostFacts;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('size_format')->alias(static fn (int $bytes): string => round($bytes / 1048576) . ' MB');

    $this->host = static fn (array $changes = []): ReleaseHostFacts => new ReleaseHostFacts(...array_merge([
        'fileChangesAllowed' => true,
        'canOpenZips'        => true,
        'unwritable'         => [],
        'freeBytes'          => 500 * 1048576,
        'laidOutAsAPackage'  => true,
    ], $changes));

    $this->reasons = static fn (ReleaseHostFacts $host, int $needed = 100 * 1048576): array => array_column($host->blockers($needed), 'reason');
});

it('finds nothing in the way on a host that can do all of it', function () {
    expect(($this->host)()->blockers(100 * 1048576))->toBe([]);
});

it('names each thing that prevents an installation', function (array $changes, string $reason, string $says) {
    $blockers = ($this->host)($changes)->blockers(100 * 1048576);

    expect(array_column($blockers, 'reason'))->toBe([$reason])
        ->and($blockers[0]['message'])->toContain($says);
})->with([
    'the site is set not to change its own files' => [['fileChangesAllowed' => false], 'file_changes_off', 'DISALLOW_FILE_MODS'],
    'PHP cannot open a zip'                       => [['canOpenZips' => false], 'no_zip', 'zip'],
    'a folder it has to replace cannot be written' => [['unwritable' => ['/home/acme/public_html/wp-content/plugins']], 'not_writable', '/home/acme/public_html/wp-content/plugins'],
    'there is not the room'                       => [['freeBytes' => 40 * 1048576], 'no_space', '40 MB'],
    'its folders are not where a package puts them' => [['laidOutAsAPackage' => false], 'layout_differs', 'wp-content/plugins'],
]);

it('names every folder that cannot be written, not only the first', function () {
    $host = ($this->host)(['unwritable' => ['/site/wp-content/plugins', '/site/wp-content/themes']]);

    expect(array_column($host->blockers(0), 'message'))->toHaveCount(2);
});

it('does not guess at room on a host that will not say how much it has', function () {
    expect(($this->reasons)(($this->host)(['freeBytes' => null])))->toBe([]);
});

it('says all of what is in the way at once', function () {
    $host = ($this->host)(['fileChangesAllowed' => false, 'canOpenZips' => false, 'freeBytes' => 1]);

    expect(($this->reasons)($host))->toBe(['file_changes_off', 'no_zip', 'no_space']);
});

it('tells a site set not to change its files the one line that allows releases', function () {
    $blockers = ($this->host)(['fileChangesAllowed' => false])->blockers(0);

    // The line as it is written in wp-config.php, so it can be copied from the screen.
    expect($blockers[0]['message'])->toContain("define( 'COREX_ALLOW_RELEASE_INSTALL', true );")
        ->and($blockers[0]['message'])->toContain('wp-config.php');
});

it('lets a release in on a site set not to change its files, when its configuration says so', function (bool $wordPressAllows, bool $configurationAllows, bool $allowed) {
    Functions\when('wp_is_writable')->justReturn(true);
    // What is asked of WordPress is asked for a release, and for nothing wider.
    Functions\expect('wp_is_file_mod_allowed')->with('corex_release_install')->andReturn($wordPressAllows);

    $host = ReleaseHostFacts::ofThisHost([sys_get_temp_dir()], $configurationAllows);

    expect($host->fileChangesAllowed)->toBe($allowed);
})->with([
    'WordPress allows it'                         => [true, false, true],
    'WordPress does not, the configuration does'  => [false, true, true],
    'neither does'                                => [false, false, false],
]);

it('is told, and does not work out, whether the site is laid out as a package is', function (bool $laidOut) {
    Functions\when('wp_is_writable')->justReturn(true);
    Functions\when('wp_is_file_mod_allowed')->justReturn(true);

    expect(ReleaseHostFacts::ofThisHost([sys_get_temp_dir()], laidOutAsAPackage: $laidOut)->laidOutAsAPackage)->toBe($laidOut);
})->with([true, false]);
