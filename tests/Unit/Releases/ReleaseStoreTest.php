<?php

/**
 * Unit test: the installer's own place on a site (spec 107, plan D5).
 *
 * `wp-content/corex-releases/` is where a package is received, unpacked and kept as the way
 * back, and where the installer remembers what it was doing. It is outside every folder a
 * release replaces, so it is still there when the release that started an installation is not.
 *
 * Run against a real folder in the temp directory.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseStore;

beforeEach(function () {
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));
    Functions\when('wp_json_encode')->alias('json_encode');

    $this->content = sys_get_temp_dir() . '/corex-content-' . bin2hex(random_bytes(4));
    mkdir($this->content);
    $this->store = new ReleaseStore($this->content);
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

it('makes its place, closed to a web server, with a folder for each thing it keeps', function () {
    $root = $this->store->root();

    expect($root)->toBe($this->content . '/corex-releases')
        ->and(file_get_contents($root . '/.htaccess'))->toContain('Require all denied')
        ->and(is_file($root . '/index.php'))->toBeTrue()
        ->and(is_file($root . '/web.config'))->toBeTrue()
        ->and(is_dir($root . '/incoming'))->toBeTrue()
        ->and(is_dir($root . '/staging'))->toBeTrue()
        ->and(is_dir($root . '/previous'))->toBeTrue();
});

it('answers where a received package is kept', function () {
    expect($this->store->fileIn('incoming', 'corex-release-acme-0.44.0-20261008-180000.zip'))
        ->toBe($this->content . '/corex-releases/incoming/corex-release-acme-0.44.0-20261008-180000.zip');
});

it('never answers a path outside its place', function (string $area, string $name) {
    expect(fn () => $this->store->fileIn($area, $name))->toThrow(InvalidArgumentException::class);
})->with([
    'a name that climbs'            => ['incoming', '../../plugins/akismet.zip'],
    'a name with a folder in it'    => ['incoming', 'nested/package.zip'],
    'a name with a backslash in it' => ['incoming', 'nested\\package.zip'],
    'an empty name'                 => ['incoming', ''],
    'a dot'                         => ['incoming', '..'],
    'an area it does not have'      => ['uploads', 'package.zip'],
]);

it('lists the packages it holds, however they came to be there, and nothing else', function () {
    // A package put in the folder through the host's file manager is offered like one that
    // was uploaded (D12). A part still arriving, a guard file and somebody's notes are not.
    $incoming = $this->store->root() . '/incoming';
    file_put_contents($incoming . '/corex-release-acme-0.44.0-20261008-180000.zip', str_repeat('z', 300));
    file_put_contents($incoming . '/corex-release-acme-0.45.0-20261101-090000.zip', str_repeat('z', 500));
    file_put_contents($incoming . '/' . str_repeat('a', 64) . '.part', 'half');
    file_put_contents($incoming . '/notes.txt', 'ask the host about the disk');
    file_put_contents($incoming . '/backup.zip', 'not a release');

    expect(array_column($this->store->packages(), 'bytes', 'name'))->toBe([
        'corex-release-acme-0.44.0-20261008-180000.zip' => 300,
        'corex-release-acme-0.45.0-20261101-090000.zip' => 500,
    ]);
});

it('remembers an installation in progress, and forgets it when told', function () {
    expect($this->store->state())->toBe([]);

    $this->store->saveState(['step' => 'staging', 'cursor' => 412, 'package' => 'corex-release-acme-0.44.0.zip']);

    expect((new ReleaseStore($this->content))->state())->toBe(['step' => 'staging', 'cursor' => 412, 'package' => 'corex-release-acme-0.44.0.zip']);

    $this->store->clearState();

    expect($this->store->state())->toBe([]);
});

it('reads a state it cannot make sense of as none', function () {
    file_put_contents($this->store->root() . '/state.json', '{"step":"stag');

    expect($this->store->state())->toBe([]);
});

it('keeps what happened, newest last, and only the last fifty', function () {
    foreach (range(1, 53) as $number) {
        $this->store->record(['event' => 'refused', 'number' => $number]);
    }

    $log = $this->store->log();

    expect($log)->toHaveCount(50)
        ->and($log[0]['number'])->toBe(4)
        ->and($log[49]['number'])->toBe(53);
});
