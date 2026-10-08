<?php

/**
 * Unit test: a release package received in parts (spec 107, plan D12).
 *
 * A package is tens of megabytes and a shared host takes a few at a time, on a connection that
 * may drop. So it arrives as parts appended to one file, each saying where it starts, and the
 * site says how much it holds so an upload that was cut off goes on from there. What it was
 * given is the file only if its hash is the one the browser computed before sending.
 *
 * Run against a real folder in the temp directory.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Config\Releases\ReleaseStore;
use Corex\Config\Releases\ReleaseUpload;
use Corex\Config\Releases\ReleaseUploadOutOfStep;

const A_PACKAGE_NAME = 'corex-release-acme-0.44.0-20261008-180000.zip';

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));

    $this->content = sys_get_temp_dir() . '/corex-content-' . bin2hex(random_bytes(4));
    mkdir($this->content);
    $this->incoming = $this->content . '/corex-releases/incoming';
    $this->upload   = new ReleaseUpload(new ReleaseStore($this->content));

    $this->package = 'PK' . str_repeat('release bytes ', 40);
    $this->hash    = hash('sha256', $this->package);
});

afterEach(function () {
    $remove = static function (string $path) use (&$remove): void {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? $remove("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    };
    $remove($this->content);
});

it('holds nothing of a package it has not been sent', function () {
    expect($this->upload->received($this->hash))->toBe(0);
});

it('appends each part where the last one ended, and says how much it holds', function () {
    expect($this->upload->append($this->hash, 0, substr($this->package, 0, 100)))->toBe(100)
        ->and($this->upload->append($this->hash, 100, substr($this->package, 100)))->toBe(strlen($this->package))
        ->and($this->upload->received($this->hash))->toBe(strlen($this->package));
});

it('refuses a part that does not start where it left off, and says where that is', function (int $offset) {
    $this->upload->append($this->hash, 0, substr($this->package, 0, 100));

    try {
        $this->upload->append($this->hash, $offset, 'more');
        $outOfStep = null;
    } catch (ReleaseUploadOutOfStep $caught) {
        $outOfStep = $caught;
    }

    // The browser sends the part again from here: a part sent twice, or one lost, costs a
    // round trip and not the upload.
    expect($outOfStep?->received)->toBe(100)
        ->and($this->upload->received($this->hash))->toBe(100);
})->with(['a part sent twice' => 0, 'a part that skips one' => 250]);

it('makes the parts the package once all of it is there and it is what was sent', function () {
    $this->upload->append($this->hash, 0, $this->package);

    $name = $this->upload->complete($this->hash, strlen($this->package), A_PACKAGE_NAME);

    expect($name)->toBe(A_PACKAGE_NAME)
        ->and(file_get_contents($this->incoming . '/' . A_PACKAGE_NAME))->toBe($this->package)
        ->and($this->upload->received($this->hash))->toBe(0);
});

it('throws away what arrived when it is not what was sent', function () {
    // The hash the browser computed is of the file on the administrator's machine. Bytes that
    // do not make it are a damaged upload, and a damaged package is not kept to be tried.
    $this->upload->append($this->hash, 0, substr($this->package, 0, -1) . '!');

    expect(fn () => $this->upload->complete($this->hash, strlen($this->package), A_PACKAGE_NAME))
        ->toThrow(ReleaseRefused::class, 'damaged')
        ->and(glob($this->incoming . '/*'))->toBe([]);
});

it('does not call a package complete before all of it has arrived', function () {
    $this->upload->append($this->hash, 0, substr($this->package, 0, 100));

    expect(fn () => $this->upload->complete($this->hash, strlen($this->package), A_PACKAGE_NAME))
        ->toThrow(ReleaseUploadOutOfStep::class)
        ->and($this->upload->received($this->hash))->toBe(100);
});

it('keeps two packages being received apart', function () {
    $other     = 'PK' . str_repeat('another release ', 30);
    $otherHash = hash('sha256', $other);

    $this->upload->append($this->hash, 0, substr($this->package, 0, 100));
    $this->upload->append($otherHash, 0, $other);
    $this->upload->append($this->hash, 100, substr($this->package, 100));
    $this->upload->complete($otherHash, strlen($other), 'corex-release-acme-0.45.0-20261101-090000.zip');
    $this->upload->complete($this->hash, strlen($this->package), A_PACKAGE_NAME);

    expect(file_get_contents($this->incoming . '/' . A_PACKAGE_NAME))->toBe($this->package)
        ->and(file_get_contents($this->incoming . '/corex-release-acme-0.45.0-20261101-090000.zip'))->toBe($other);
});

it('takes a package only under a package\'s name', function (string $name) {
    $this->upload->append($this->hash, 0, $this->package);

    expect(fn () => $this->upload->complete($this->hash, strlen($this->package), $name))
        ->toThrow(ReleaseRefused::class)
        ->and(glob($this->content . '/*.{php,zip}', GLOB_BRACE))->toBe([]);
})->with([
    'a name that climbs out of the folder' => '../../plugins/corex-release-x.zip',
    'a script'                             => 'corex-release-acme.php',
    'a script that says it is a zip'       => 'corex-release-acme.zip.php',
    'a zip that is not a release'          => 'backup.zip',
    'a name with a folder in it'           => 'nested/corex-release-acme.zip',
    'nothing'                              => '',
]);

it('refuses a hash that is not one, which would otherwise name a file', function (string $hash) {
    expect(fn () => $this->upload->append($hash, 0, 'bytes'))->toThrow(ReleaseRefused::class)
        ->and(fn () => $this->upload->received($hash))->toThrow(ReleaseRefused::class);
})->with([
    'a path'           => '../../../wp-config',
    'too short'        => 'abc123',
    'the wrong letters' => str_repeat('g', 64),
    'upper case'       => str_repeat('A', 64),
]);

it('stops taking parts of a package that has grown past what a release can be', function () {
    // An upload has no size but what it says, and a site has only so much disk: the part
    // that would take it past the limit is refused, and what had arrived is thrown away.
    $upload = new ReleaseUpload(new ReleaseStore($this->content), largest: 150);
    $upload->append($this->hash, 0, substr($this->package, 0, 100));

    expect(fn () => $upload->append($this->hash, 100, substr($this->package, 100, 100)))
        ->toThrow(ReleaseRefused::class, 'larger')
        ->and($upload->received($this->hash))->toBe(0);
});
