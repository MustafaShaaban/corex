<?php

/**
 * Unit test: a release package inspected where it landed, before anything of it is unpacked
 * (spec 107, US2; plan D3, D4).
 *
 * Every zip here is a real one, built in the temp folder and opened by the same `ZipArchive` a
 * site opens an uploaded package with. What a wrong package costs is the thing under test: a
 * reason an administrator can act on, and nothing else. Nothing on the site may have changed.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\InstalledRelease;
use Corex\Config\Releases\ReleasePackageInspector;
use Corex\Config\Releases\ReleaseRefused;
use Corex\Tests\Support\ReleasePackages;

beforeEach(function () {
    Functions\when('__')->returnArg();

    // A site's root, with nothing in it: the inspector is given it only through what is
    // installed, and must leave it exactly as it was.
    $this->site = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
    mkdir($this->site);

    $this->installed = static fn (array|false $recorded = false) => Functions\when('get_option')->justReturn($recorded);
    ($this->installed)(['corex_version' => '0.43.5', 'client' => 'acme']);

    $this->inspector = fn (string $php = '8.3.6', string $wordPress = '7.1.3') => new ReleasePackageInspector(
        new InstalledRelease($this->site),
        $php,
        $wordPress,
    );
});

afterEach(function () {
    ReleasePackages::clean();
    array_map('unlink', glob($this->site . '/*') ?: []);
    rmdir($this->site);
});

it('says what a good package is, and what it would replace', function () {
    $inspection = ($this->inspector)()->inspect(ReleasePackages::package());

    expect($inspection->manifest->version)->toBe('0.44.0')
        ->and($inspection->manifest->builtAt->format('Y-m-d H:i'))->toBe('2026-10-08 18:00')
        ->and($inspection->manifest->client)->toBe('acme')
        ->and($inspection->replaces)->toBe('0.43.5')
        ->and($inspection->older)->toBeFalse()
        ->and($inspection->clientUnconfirmed)->toBeFalse()
        ->and($inspection->files)->toBe(45)
        ->and($inspection->bytes)->toBe(4340);
});

it('says an older release than the one running needs saying twice', function () {
    ($this->installed)(['corex_version' => '0.45.0', 'client' => 'acme']);

    expect(($this->inspector)()->inspect(ReleasePackages::package())->older)->toBeTrue();
});

it('asks for the client to be confirmed on a site that has never said which it is', function () {
    ($this->installed)(false);

    $inspection = ($this->inspector)()->inspect(ReleasePackages::package());

    expect($inspection->clientUnconfirmed)->toBeTrue()
        ->and($inspection->replaces)->toBeNull()
        ->and($inspection->older)->toBeFalse();
});

/**
 * Each wrong package, the reason it is refused for, and a word its sentence has to carry: the
 * thing an administrator needs in order to do something about it.
 */
dataset('wrong packages', [
    'a file that is not a zip' => [
        function () {
            $file = (string) tempnam(sys_get_temp_dir(), 'corex-not-a-zip-');
            file_put_contents($file, 'plain text');

            return $file;
        },
        ReleaseRefused::NOT_A_ZIP,
        'not a zip',
    ],
    'a zip that describes nothing' => [
        fn () => ReleasePackages::zip(['readme.txt' => 'hello']),
        ReleaseRefused::NOT_A_PACKAGE,
        'corex-release.json',
    ],
    'a zip of the dist folder, not of what is in it' => [
        fn () => ReleasePackages::zip(['dist/corex-release.json' => (string) json_encode(ReleasePackages::description())]),
        ReleaseRefused::NOT_A_PACKAGE,
        'folder',
    ],
    'a package built before packages described themselves' => [
        fn () => ReleasePackages::package(['schema' => null]),
        ReleaseRefused::BUILT_BEFORE,
        'Build it again',
    ],
    'a package for another client' => [
        fn () => ReleasePackages::package(['client' => 'globex']),
        ReleaseRefused::OTHER_CLIENT,
        'globex',
    ],
    'the framework alone, for a client\'s site' => [
        fn () => ReleasePackages::package(['client' => null]),
        ReleaseRefused::OTHER_CLIENT,
        'acme',
    ],
    'a release that needs a newer PHP' => [
        fn () => ReleasePackages::package(['requires' => ['php' => '8.4', 'wordpress' => '7.0']]),
        ReleaseRefused::NEEDS_PHP,
        '8.3.6',
    ],
    'a release that needs a newer WordPress' => [
        fn () => ReleasePackages::package(['requires' => ['php' => '8.3', 'wordpress' => '7.2']]),
        ReleaseRefused::NEEDS_WORDPRESS,
        '7.1.3',
    ],
    'a folder the package says it holds and does not' => [
        fn () => ReleasePackages::package(without: ['wp-content/themes/corex']),
        ReleaseRefused::MISSING_FOLDER,
        'wp-content/themes/corex',
    ],
    'an entry that climbs out of its folder' => [
        fn () => ReleasePackages::package(more: ['wp-content/plugins/acme-site/../../../wp-config.php' => '<?php']),
        ReleaseRefused::UNSAFE_ENTRY,
        '../',
    ],
    'an entry with an absolute path' => [
        fn () => ReleasePackages::package(more: ['/etc/cron.d/corex' => '* * * * *']),
        ReleaseRefused::UNSAFE_ENTRY,
        '/etc/cron.d/corex',
    ],
    'an entry on a drive' => [
        fn () => ReleasePackages::package(more: ['C:/Windows/corex.ini' => '']),
        ReleaseRefused::UNSAFE_ENTRY,
        'C:/Windows/corex.ini',
    ],
    'an entry a release never holds' => [
        fn () => ReleasePackages::package(more: ['wp-content/plugins/corex-core/.git/config' => '[core]']),
        ReleaseRefused::FORBIDDEN_ENTRY,
        '.git',
    ],
    'a site\'s configuration, under another case' => [
        fn () => ReleasePackages::package(more: ['WP-Config.php' => '<?php']),
        ReleaseRefused::FORBIDDEN_ENTRY,
        'WP-Config.php',
    ],
]);

it('refuses a wrong package for what is wrong with it, and touches nothing', function (Closure $package, string $reason, string $says) {
    $before = scandir($this->site);

    try {
        ($this->inspector)()->inspect($package());
        $refused = null;
    } catch (ReleaseRefused $caught) {
        $refused = $caught;
    }

    expect($refused)->toBeInstanceOf(ReleaseRefused::class)
        ->and($refused->reason)->toBe($reason)
        ->and($refused->getMessage())->toContain($says)
        ->and(scandir($this->site))->toBe($before);
})->with('wrong packages');

it('holds the same list of what a release never holds as the builder does', function () {
    // The builder refuses these when it makes a package and a site refuses them when it is
    // given one. Two lists in two languages: this file is what holds each to the other, with
    // `tests/build-shared-host-dist.test.js` on the builder's side.
    $recorded = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/Releases/forbidden-segments.json'), true);

    expect(ReleasePackageInspector::FORBIDDEN_SEGMENTS)->toBe($recorded);
});
