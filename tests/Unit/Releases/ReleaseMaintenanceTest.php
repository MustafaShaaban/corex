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
 * Each reading runs in a PHP process of its own: the gate defines a constant when it lets a
 * request through, saying where the key was, and a constant cannot be taken back.
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
 * WordPress's reading of the file, for a request with the given server variables, query and
 * headers as the web server holds them, in a PHP process of its own.
 *
 * @param array<string,mixed>       $server  What PHP was handed as `$_SERVER`.
 * @param array<string,mixed>       $get     The query.
 * @param array<string,string>|null $headers What `getallheaders()` answers; null where PHP has no such function.
 *
 * @return array{inMaintenance:bool,scrapeBypassOpen:bool,keyFrom:string}
 */
function wordPressReads(string $file, array $server = [], array $get = [], ?array $headers = null): array
{
    $reader = $file . '.reader.php';
    file_put_contents($reader, '<?php
        $request = json_decode(stream_get_contents(STDIN), true);
        if (is_array($request["headers"])) {
            function getallheaders(): array { return $GLOBALS["request"]["headers"]; }
        }
        define("ABSPATH", __DIR__ . "/");
        require ' . var_export(dirname(__DIR__, 3) . '/plugins/corex-config/src/Releases/ReleaseMaintenance.php', true) . ';
        $read = static function () use ($request): mixed {
            global $upgrading;
            $_SERVER = array_merge($_SERVER, $request["server"]);
            $_GET    = $request["get"];
            require ' . var_export($file, true) . ';
            return $upgrading;
        };
        $upgrading = $read();
        echo json_encode([
            "inMaintenance"    => (time() - $upgrading) < 600,
            // WordPress lets a request carrying the MD5 of an integer `$upgrading` through.
            "scrapeBypassOpen" => is_int($upgrading),
            "keyFrom"          => Corex\Config\Releases\ReleaseMaintenance::keyCameFrom(),
        ]);
    ');

    $process = proc_open([PHP_BINARY, $reader], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
    fwrite($pipes[0], (string) json_encode(['server' => $server, 'get' => $get, 'headers' => $headers]));
    fclose($pipes[0]);
    $read = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($process);
    unlink($reader);

    return json_decode($read, true) ?? throw new RuntimeException('The reading said: ' . $read);
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
        ->and(wordPressReads($this->site . '/.maintenance', headers: ['X-Corex-Release-Key' => 'a-guess'])['inMaintenance'])->toBeTrue()
        // Not a string: an argument sent as a list.
        ->and(wordPressReads($this->site . '/.maintenance', get: ['corex_release_key' => [THE_KEY]])['inMaintenance'])->toBeTrue();
});

it('lets through the one request that carries the installation\'s key, and says where it was', function (array $server, array $get, ?array $headers, string $from) {
    $this->maintenance->begin(hash('sha256', THE_KEY), time());

    expect(wordPressReads($this->site . '/.maintenance', $server, $get, $headers))
        ->toMatchArray(['inMaintenance' => false, 'keyFrom' => $from]);
})->with([
    'in its header, as PHP was handed it' => [['HTTP_X_COREX_RELEASE_KEY' => THE_KEY], [], null, ReleaseMaintenance::FROM_HEADER],
    // The host of 2026-10-18 hands PHP no `Authorization` header. One that keeps this one from
    // PHP too may still hold it where the web server keeps the request's headers.
    'in its header, as the web server holds it, spelt as any client spells it' => [[], [], ['x-corex-release-key' => THE_KEY], ReleaseMaintenance::FROM_SERVER],
    'in its address, as the screen and the recovery link send it' => [[], ['corex_release_key' => THE_KEY], ['Accept' => '*/*'], ReleaseMaintenance::FROM_ADDRESS],
    'in its address, where a stale key is in its header' => [['HTTP_X_COREX_RELEASE_KEY' => 'a-stale-key'], ['corex_release_key' => THE_KEY], null, ReleaseMaintenance::FROM_ADDRESS],
]);

it('says no key was needed by a request that came in after maintenance ended by itself', function () {
    $this->maintenance->begin(hash('sha256', THE_KEY), time() - 601);

    expect(wordPressReads($this->site . '/.maintenance')['keyFrom'])->toBe(ReleaseMaintenance::NOT_NEEDED);
});

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
