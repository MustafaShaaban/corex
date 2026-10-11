<?php

/**
 * Unit test: where an installation stands, as it is kept between its requests (spec 107, plan
 * D5 and D8; FR-022).
 *
 * `state.json` is written by one release and read by the next: the requests after the swap are
 * answered by the code just installed. What is kept, and what a release does with a record it
 * cannot read, is a contract between them.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseProgress;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Config\Releases\ReleaseStore;
use Corex\Tests\Support\ReleasePackages;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));
    Functions\when('wp_json_encode')->alias('json_encode');

    $this->content = sys_get_temp_dir() . '/corex-content-' . bin2hex(random_bytes(4));
    mkdir($this->content);
    $this->store = new ReleaseStore($this->content);

    $this->prepared = new ReleaseProgress(
        step: ReleaseProgress::AWAITING,
        cursor: 0,
        of: 0,
        package: 'corex-release-acme-0.44.0-20261008-180000.zip',
        packageHash: str_repeat('a', 64),
        manifest: (string) json_encode(ReleasePackages::description()),
        keyHash: str_repeat('b', 64),
        by: 7,
        preparedAt: '2026-10-11T12:00:00+00:00',
        older: false,
        clientUnconfirmed: true,
        replaced: ['corex_version' => '0.43.5', 'client' => 'acme'],
    );
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

it('finds no installation on a site where none was started', function () {
    expect(ReleaseProgress::standing($this->store))->toBeNull();
});

it('is read back as it was kept, by the release that wrote it or the next', function () {
    $stoodAt = $this->prepared
        ->at(ReleaseProgress::FINISH, 20)
        ->with(['changes' => [['site' => 1, 'component' => 'newsletter', 'from' => '', 'to' => '1']], 'failed' => 'A change failed.']);
    $stoodAt->save($this->store);

    expect(ReleaseProgress::standing($this->store))->toEqual($stoodAt)
        ->and($this->store->state()['protocol'])->toBe(1);
});

it('keeps the hash of the installation\'s key, and not the key', function () {
    $this->prepared->save($this->store);

    expect(array_keys($this->store->state()))->not->toContain('key')
        ->and($this->store->state()['key_hash'])->toBe(str_repeat('b', 64));
});

it('refuses, in words, a record written by a release it does not know', function () {
    $this->prepared->save($this->store);
    $this->store->saveState([...$this->store->state(), 'protocol' => 2]);

    expect(fn () => ReleaseProgress::standing($this->store))
        ->toThrow(function (ReleaseRefused $refused) {
            expect($refused->reason)->toBe(ReleaseProgress::UNREADABLE)
                ->and($refused->getMessage())->toContain('recovery link');
        });
});

it('says which steps come before anything of the site has moved', function (string $step, bool $before) {
    expect($this->prepared->at($step)->isBeforeTheSwap())->toBe($before);
})->with([
    [ReleaseProgress::AWAITING, true],
    [ReleaseProgress::UNPACK, true],
    [ReleaseProgress::VERIFY, true],
    [ReleaseProgress::COPY_TABLES, true],
    [ReleaseProgress::SWAP, false],
    [ReleaseProgress::FINISH, false],
    [ReleaseProgress::STUCK, false],
]);

it('waits fifteen minutes to be confirmed', function () {
    expect($this->prepared->waitsUntil()->format(DATE_ATOM))->toBe('2026-10-11T12:15:00+00:00');
});

it('tells the screen where it stands and whose it is, and nothing that could pass for it', function () {
    $told = $this->prepared->at(ReleaseProgress::UNPACK, 1200, 7340)->forTheScreen();

    expect($told)->toBe([
        'step'            => 'unpack',
        'cursor'          => 1200,
        'of'              => 7340,
        'version'         => '0.44.0',
        'package'         => 'corex-release-acme-0.44.0-20261008-180000.zip',
        'by'              => 7,
        'prepared_at'     => '2026-10-11T12:00:00+00:00',
        'before_the_swap' => true,
        'failed'          => null,
    ]);
});
