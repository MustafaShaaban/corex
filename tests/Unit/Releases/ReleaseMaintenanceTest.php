<?php

/**
 * Unit test: WordPress's maintenance answer while a release is being put in place (spec 107,
 * plan D7; FR-024).
 *
 * The file is read here the way WordPress reads it (`wp_is_maintenance_mode()`, in
 * `wp-includes/load.php`): included inside a function with `$upgrading` global, and the site is
 * in maintenance while that is less than ten minutes old. A real WordPress answering a visitor
 * with it is the browser test's to show.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseHostFacts;
use Corex\Config\Releases\ReleaseMaintenance;
use Corex\Config\Releases\ReleaseRefused;

const THE_KEY = 'f3b1c2d4e5a697887766554433221100ffeeddccbbaa99887766554433221100';

/**
 * WordPress's reading of the file, for a request with the given header and query.
 *
 * @return array{inMaintenance:bool,scrapeBypassOpen:bool}
 */
function wordPressReads(string $file, array $server = [], array $get = []): array
{
    $read = static function () use ($file, $server, $get): mixed {
        global $upgrading;
        $_SERVER = array_merge($_SERVER, $server);
        $_GET    = $get;

        require $file;

        return $upgrading;
    };

    $kept      = [$_SERVER, $_GET];
    $upgrading = $read();
    [$_SERVER, $_GET] = $kept;

    return [
        'inMaintenance'    => (time() - $upgrading) < 600,
        // WordPress lets a request carrying the MD5 of an integer `$upgrading` through.
        'scrapeBypassOpen' => is_int($upgrading),
    ];
}

beforeEach(function () {
    Functions\when('__')->returnArg();

    $this->site = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
    mkdir($this->site);
    $this->maintenance = new ReleaseMaintenance($this->site);
});

afterEach(function () {
    array_map('unlink', glob($this->site . '/{,.}[!.]*', GLOB_BRACE) ?: []);
    rmdir($this->site);
});

it('keeps every request out, as WordPress reads it', function () {
    $this->maintenance->begin(hash('sha256', THE_KEY), time());

    expect($this->maintenance->isOn())->toBeTrue()
        ->and(wordPressReads($this->site . '/.maintenance')['inMaintenance'])->toBeTrue()
        ->and(wordPressReads($this->site . '/.maintenance', get: ['corex_release_key' => 'a-guess'])['inMaintenance'])->toBeTrue()
        ->and(wordPressReads($this->site . '/.maintenance', server: ['HTTP_X_COREX_RELEASE_KEY' => ''])['inMaintenance'])->toBeTrue()
        // Not a string: an argument sent as a list.
        ->and(wordPressReads($this->site . '/.maintenance', get: ['corex_release_key' => [THE_KEY]])['inMaintenance'])->toBeTrue();
});

it('lets through the one request that carries the installation\'s key', function (array $server, array $get) {
    $this->maintenance->begin(hash('sha256', THE_KEY), time());

    expect(wordPressReads($this->site . '/.maintenance', $server, $get)['inMaintenance'])->toBeFalse();
})->with([
    'in its header, as the finishing request does' => [['HTTP_X_COREX_RELEASE_KEY' => THE_KEY], []],
    'in its address, as the recovery link does'    => [[], ['corex_release_key' => THE_KEY]],
]);

it('holds the key\'s hash and never the key', function () {
    $this->maintenance->begin(hash('sha256', THE_KEY), time());

    $written = (string) file_get_contents($this->site . '/.maintenance');

    expect($written)->toContain(hash('sha256', THE_KEY))
        ->and($written)->not->toContain(THE_KEY);
});

it('does not leave open the way WordPress lets its own editor through', function () {
    // WordPress lets a request through maintenance when it carries the MD5 of `$upgrading`,
    // if that is an integer: the time maintenance began, which can be guessed.
    $this->maintenance->begin(hash('sha256', THE_KEY), time());

    expect(wordPressReads($this->site . '/.maintenance')['scrapeBypassOpen'])->toBeFalse();
});

it('stops applying by itself, by WordPress\'s own rule, ten minutes after it began', function () {
    $this->maintenance->begin(hash('sha256', THE_KEY), time() - 601);

    expect(wordPressReads($this->site . '/.maintenance')['inMaintenance'])->toBeFalse();
});

it('ends: the file is gone, and ending twice is nothing', function () {
    $this->maintenance->begin(hash('sha256', THE_KEY), time());

    $this->maintenance->end();
    $this->maintenance->end();

    expect($this->maintenance->isOn())->toBeFalse()
        ->and(is_file($this->site . '/.maintenance'))->toBeFalse();
});

it('refuses in words where the site cannot write its own root, and an installation does not go on', function () {
    // A root that is not there stands in for one the host will not let the site write in.
    $nowhere = new ReleaseMaintenance($this->site . '/not-a-folder');

    try {
        $nowhere->begin(hash('sha256', THE_KEY), time());
    } catch (ReleaseRefused $refused) {
        expect($refused->reason)->toBe(ReleaseHostFacts::NOT_WRITABLE);
    }

    expect(isset($refused))->toBeTrue();
});
