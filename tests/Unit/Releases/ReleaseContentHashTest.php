<?php

/**
 * Unit test: the hash of a release folder, computed where a package lands (spec 107, plan D2).
 *
 * The builder computes it in Node when the package is made. The recorded folder and its recorded
 * description are the same two files `tests/release-content-hash.test.js` holds the Node side to,
 * so a change to either implementation that the other does not follow fails one of the two.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Corex\Config\Releases\ReleaseContentHash;

const RELEASE_FIXTURES = __DIR__ . '/../../Fixtures/Releases';

it('describes the recorded folder exactly as the builder does', function () {
    $recorded = json_decode((string) file_get_contents(RELEASE_FIXTURES . '/hashed.json'), true);

    expect((new ReleaseContentHash())->describe(RELEASE_FIXTURES . '/hashed'))->toBe($recorded);
});

it('does not mind how the folder is named to it', function (string $suffix) {
    $hasher = new ReleaseContentHash();

    expect($hasher->describe(RELEASE_FIXTURES . '/hashed' . $suffix))
        ->toBe($hasher->describe(RELEASE_FIXTURES . '/hashed'));
})->with(['a trailing slash' => '/', 'a trailing backslash' => '\\']);

it('says a folder that is not there holds nothing, with the hash of nothing', function () {
    expect((new ReleaseContentHash())->describe(RELEASE_FIXTURES . '/no-such-folder'))->toBe([
        'files' => 0,
        'bytes' => 0,
        'hash'  => hash('sha256', ''),
    ]);
});
