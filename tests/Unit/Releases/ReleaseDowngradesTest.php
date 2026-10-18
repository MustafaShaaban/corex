<?php

/**
 * Unit test: the plugins and themes a package would put back to an older version than the site
 * has (spec 107, FR-029; DECISIONS #370).
 *
 * Perego's package of 2026-10-18 held six third-party plugins older than the copies updated in
 * the site's admin since it was built. Installing it would have put all six back without a word.
 * Here the site is real folders in the temp folder and the package a real zip, read where it
 * landed.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Corex\Config\Releases\ReleaseDowngrades;
use Corex\Config\Releases\ReleaseManifest;
use Corex\Tests\Support\ReleasePackages;

const ACF = 'wp-content/plugins/advanced-custom-fields-pro';

/** A plugin's main file as an author writes one, with the header WordPress reads. */
function pluginFile(string $name, string $version): string
{
    return "<?php\r\n/**\r\n * Plugin Name: {$name}\r\n * Version: {$version} */\r\n";
}

function themeFile(string $name, string $version): string
{
    return "/*\nTheme Name: {$name}\nVersion: {$version}\n*/\n";
}

beforeEach(function () {
    $this->site = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
    mkdir($this->site);

    /** Put files on the site, by path. */
    $this->onTheSite = function (array $files): void {
        foreach ($files as $path => $holds) {
            if (! is_dir(dirname("{$this->site}/{$path}"))) {
                mkdir(dirname("{$this->site}/{$path}"), 0777, true);
            }
            file_put_contents("{$this->site}/{$path}", $holds);
        }
    };

    /** What the check finds for a package of 0.45.0 that also holds ACF, with these files in it. */
    $this->downgradesIn = function (array $files): array {
        $paths    = [...ReleasePackages::PATHS, ACF];
        $contents = array_fill_keys($paths, ['files' => 1, 'bytes' => 1, 'hash' => str_repeat('a', 64)]);
        $package  = ReleasePackages::package(['corex_version' => '0.45.0', 'release_paths' => $paths, 'contents' => $contents], $files);

        $zip = new ZipArchive();
        $zip->open($package);

        try {
            return (new ReleaseDowngrades($this->site))->in($zip, ReleaseManifest::fromJson((string) $zip->getFromName('corex-release.json')));
        } finally {
            $zip->close();
        }
    };
});

afterEach(function () {
    ReleasePackages::clean();

    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->site, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($this->site);
});

it('names each plugin and theme the package holds an older version of, by the site\'s name for it, with both versions', function () {
    // ACF's main file is acf.php, after a file at the folder's top that is not the plugin's.
    ($this->onTheSite)([
        ACF . '/acf.php'                    => pluginFile('Advanced Custom Fields PRO', '6.8.6'),
        ACF . '/includes.php'               => '<?php // no header',
        'wp-content/themes/corex/style.css' => themeFile('CoreX', '2.1.0'),
    ]);

    expect(($this->downgradesIn)([
        ACF . '/acf.php'                    => pluginFile('ACF PRO', '6.8.4'),
        ACF . '/includes.php'               => '<?php // no header',
        'wp-content/themes/corex/style.css' => themeFile('CoreX', '2.0.9'),
    ]))->toBe([
        ['path' => 'wp-content/themes/corex', 'name' => 'CoreX', 'site' => '2.1.0', 'package' => '2.0.9'],
        ['path' => ACF, 'name' => 'Advanced Custom Fields PRO', 'site' => '6.8.6', 'package' => '6.8.4'],
    ]);
});

it('compares nothing it cannot read on both sides, and leaves CoreX\'s own folders to the older-release question', function () {
    ($this->onTheSite)([
        // The same version, and a newer one in the package.
        ACF . '/acf.php'                               => pluginFile('Advanced Custom Fields PRO', '6.8.6'),
        'wp-content/themes/corex/style.css'            => themeFile('CoreX', '2.0.0'),
        // CoreX's own plugin carries the release's version: FR-015 asks about going back on it.
        'wp-content/plugins/corex-core/corex-core.php' => pluginFile('CoreX Core', '0.46.0'),
        // A header with no version.
        'wp-content/plugins/acme-site/acme-site.php'  => "<?php\n/* Plugin Name: Acme */",
    ]);

    expect(($this->downgradesIn)([
        ACF . '/acf.php'                               => pluginFile('Advanced Custom Fields PRO', '6.8.6'),
        'wp-content/themes/corex/style.css'            => themeFile('CoreX', '2.0.1'),
        'wp-content/plugins/corex-core/corex-core.php' => pluginFile('CoreX Core', '0.45.0'),
        'wp-content/plugins/acme-site/acme-site.php'   => pluginFile('Acme', '1.0.0'),
    ]))->toBe([]);
});

it('compares nothing with a folder that is not on the site', function () {
    expect(($this->downgradesIn)([ACF . '/acf.php' => pluginFile('Advanced Custom Fields PRO', '6.8.4')]))->toBe([]);
});
