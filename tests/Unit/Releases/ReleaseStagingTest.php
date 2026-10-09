<?php

/**
 * Unit test: a package unpacked beside the site, and held to its own description (spec 107,
 * plan D8: the unpack and verify steps; FR-013).
 *
 * Nothing here is on the live site. A release is unpacked into the installer's own folder and
 * compared with what the package says it holds, folder by folder, before any folder of the site
 * is moved. A host cuts a request off after some seconds, so unpacking answers where it has got
 * to and goes on from there.
 *
 * Real zips, unpacked into a real folder in the temp directory.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseContentHash;
use Corex\Config\Releases\ReleaseManifest;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Config\Releases\ReleaseStaging;
use Corex\Config\Releases\ReleaseStore;
use Corex\Tests\Support\ReleasePackages;

const A_RELEASE = [
    'wp-content/plugins/corex-core/corex-core.php'  => '<?php // the plugin',
    'wp-content/plugins/corex-core/src/Boot.php'    => '<?php // boots',
    'wp-content/plugins/corex-core/assets/app.css'  => 'body{}',
    'wp-content/themes/corex/style.css'             => '/* Theme Name: CoreX */',
    'wp-content/vendor/autoload.php'                => '<?php // autoload',
];

/** WordPress itself, which a package also holds and an installation does not replace. */
const NOT_THE_RELEASES = [
    'wp-includes/version.php' => '<?php $wp_version = "7.1.3";',
    'index.php'               => '<?php // WordPress',
];

function manifestOf(string $zipFile): ReleaseManifest
{
    $zip = new ZipArchive();
    $zip->open($zipFile);
    $described = (string) $zip->getFromName('corex-release.json');
    $zip->close();

    return ReleaseManifest::fromJson($described);
}

/** Every file under a folder, by its path inside it, with what it holds. */
function filesUnder(string $folder): array
{
    $files = [];

    if (! is_dir($folder)) {
        return $files;
    }

    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
    foreach ($entries as $entry) {
        $files[str_replace('\\', '/', substr($entry->getPathname(), strlen($folder) + 1))] = (string) file_get_contents($entry->getPathname());
    }
    ksort($files);

    return $files;
}

/** Unpack a package to the end, however many requests it takes. */
function unpackAll(ReleaseStaging $staging, string $zipFile, ReleaseManifest $manifest): int
{
    $requests = 0;
    $cursor   = 0;

    do {
        $progress = $staging->unpack($zipFile, $manifest, $cursor);
        $cursor   = $progress['cursor'];
        $requests++;
    } while ($cursor < $progress['of']);

    return $requests;
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));

    $this->content = sys_get_temp_dir() . '/corex-content-' . bin2hex(random_bytes(4));
    mkdir($this->content);
    $this->store    = new ReleaseStore($this->content);
    $this->staged   = $this->content . '/corex-releases/staging';
    $this->staging  = new ReleaseStaging($this->store, new ReleaseContentHash());
    $this->package  = ReleasePackages::faithful(A_RELEASE, NOT_THE_RELEASES);
    $this->manifest = manifestOf($this->package);
});

afterEach(function () {
    ReleasePackages::clean();

    $remove = static function (string $path) use (&$remove): void {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? $remove("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    };
    $remove($this->content);
});

it('unpacks what the release owns, as it is in the package, and nothing else the package holds', function () {
    unpackAll($this->staging, $this->package, $this->manifest);

    $unpacked = filesUnder($this->staged);
    $expected = A_RELEASE;
    ksort($expected);

    expect($unpacked)->toBe($expected);
});

it('answers where it has got to, and goes on from there without doing any of it again', function () {
    // A host that cuts a request off after a second: every request unpacks what it can and
    // says where the next one starts.
    $hurried = new ReleaseStaging($this->store, new ReleaseContentHash(), seconds: 0.0);

    // The first requests, as far as the first file of the release.
    $cursor = 0;
    do {
        $progress = $hurried->unpack($this->package, $this->manifest, $cursor);
        $cursor   = $progress['cursor'];
    } while (filesUnder($this->staged) === []);

    expect($cursor)->toBeLessThan($progress['of']);

    // What a request wrote is not written again by the ones after it.
    $written = array_key_first(filesUnder($this->staged));
    unlink($this->staged . '/' . $written);

    while ($cursor < $progress['of']) {
        $cursor = $hurried->unpack($this->package, $this->manifest, $cursor)['cursor'];
    }

    $expected = A_RELEASE;
    unset($expected[$written]);
    ksort($expected);

    expect(filesUnder($this->staged))->toBe($expected);
});

it('starts from nothing: what an attempt before it left is not part of this one', function () {
    mkdir($this->staged . '/wp-content/plugins/corex-core', 0777, true);
    file_put_contents($this->staged . '/wp-content/plugins/corex-core/left-behind.php', '<?php // from last time');

    unpackAll($this->staging, $this->package, $this->manifest);

    expect(filesUnder($this->staged))->not->toHaveKey('wp-content/plugins/corex-core/left-behind.php');
});

it('finds every folder to be what the package says it holds', function () {
    unpackAll($this->staging, $this->package, $this->manifest);

    foreach ($this->manifest->releasePaths as $path) {
        $this->staging->verify($this->manifest, $path);
    }

    expect($this->staging->folderOf('wp-content/themes/corex'))->toBe($this->staged . '/wp-content/themes/corex');
});

it('refuses a folder that is not what the package says it holds, and keeps none of it', function (Closure $damage) {
    unpackAll($this->staging, $this->package, $this->manifest);
    $damage($this->staged . '/wp-content/plugins/corex-core');

    try {
        $this->staging->verify($this->manifest, 'wp-content/plugins/corex-core');
    } catch (ReleaseRefused $refused) {
        expect($refused->reason)->toBe(ReleaseStaging::DIFFERS)
            ->and($refused->getMessage())->toContain('wp-content/plugins/corex-core');
    }

    expect(isset($refused))->toBeTrue()
        ->and(filesUnder($this->staged))->toBe([]);
})->with([
    // The same size: only the hash can tell.
    'a file changed, byte for byte the same length' => [static fn (string $folder) => file_put_contents($folder . '/src/Boot.php', '<?php // b00ts')],
    'a file that was cut short'                     => [static fn (string $folder) => file_put_contents($folder . '/src/Boot.php', '<?php')],
    'a file the package never held'                 => [static fn (string $folder) => file_put_contents($folder . '/src/Extra.php', '<?php')],
    'a file that did not arrive'                    => [static fn (string $folder) => unlink($folder . '/assets/app.css')],
]);

it('writes nothing for a file whose path climbs out of the folder it is in', function () {
    // The inspection refuses this package before unpacking is ever asked for. Unpacking does
    // not rely on having been asked second.
    $climbing = ReleasePackages::faithful(A_RELEASE, [
        'wp-content/plugins/corex-core/../../../../escaped.php' => '<?php // outside',
    ]);

    try {
        unpackAll($this->staging, $climbing, manifestOf($climbing));
    } catch (ReleaseRefused $refused) {
        expect($refused->reason)->toBe(ReleaseRefused::UNSAFE_ENTRY);
    }

    $anywhere = array_filter(
        array_keys(filesUnder($this->content)),
        static fn (string $file): bool => str_contains($file, 'escaped'),
    );

    expect(isset($refused))->toBeTrue()
        ->and(filesUnder($this->staged))->toBe([])
        ->and($anywhere)->toBe([])
        ->and(is_file(dirname($this->content) . '/escaped.php'))->toBeFalse();
});
