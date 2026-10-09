<?php

/**
 * A site in the temp folder for the installer's tests: real folders, with a release staged in
 * the installer's own place, ready to be put in (spec 107).
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\Releases\ReleaseContentHash;
use Corex\Config\Releases\ReleaseJournal;
use Corex\Config\Releases\ReleaseManifest;
use Corex\Config\Releases\ReleaseStaging;
use Corex\Config\Releases\ReleaseStore;
use Corex\Config\Releases\ReleaseSwap;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

final class ReleaseSite
{
    /** What the installer writes for itself, which is not the site: its place, its two files. */
    private const THE_INSTALLERS = ['wp-content/corex-releases/', 'wp-content/mu-plugins/corex-release-recovery.php', '.maintenance'];

    public readonly string $root;
    public readonly ReleaseStore $store;
    public readonly ReleaseStaging $staging;
    public readonly ReleaseJournal $journal;
    public readonly ReleaseManifest $manifest;

    /**
     * @param array<string,string> $before  Every file on the site before, by path, with what it holds.
     * @param array<string,string> $release The release to stage, as `ReleasePackages::faithful()` takes it.
     */
    public function __construct(array $before, array $release)
    {
        $this->root = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        self::write($this->root, $before);

        $this->store   = new ReleaseStore($this->root . '/wp-content');
        $this->staging = new ReleaseStaging($this->store, new ReleaseContentHash());
        $this->journal = new ReleaseJournal($this->store);

        $package = ReleasePackages::faithful($release);
        $zip     = new ZipArchive();
        $zip->open($package);
        $this->manifest = ReleaseManifest::fromJson((string) $zip->getFromName('corex-release.json'));
        $zip->close();

        for ($cursor = 0, $of = 1; $cursor < $of;) {
            ['cursor' => $cursor, 'of' => $of] = $this->staging->unpack($package, $this->manifest, $cursor);
        }
    }

    public function swap(): ReleaseSwap
    {
        return new ReleaseSwap($this->store, $this->staging, $this->journal, $this->root);
    }

    /**
     * Every file of the site that is not the installer's own, by path, with what it holds.
     *
     * @return array<string,string>
     */
    public function files(): array
    {
        $files   = [];
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));

        foreach ($entries as $entry) {
            $path = str_replace('\\', '/', substr($entry->getPathname(), strlen($this->root) + 1));

            if (! self::isTheInstallers($path)) {
                $files[$path] = (string) file_get_contents($entry->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    public function remove(): void
    {
        self::delete($this->root);
        ReleasePackages::clean();
    }

    /**
     * @param array<string,string> $files
     */
    private static function write(string $root, array $files): void
    {
        foreach ($files as $path => $holds) {
            if (! is_dir(dirname("$root/$path"))) {
                mkdir(dirname("$root/$path"), 0777, true);
            }
            file_put_contents("$root/$path", $holds);
        }
    }

    private static function isTheInstallers(string $path): bool
    {
        foreach (self::THE_INSTALLERS as $own) {
            if (str_starts_with($path, $own)) {
                return true;
            }
        }

        return false;
    }

    private static function delete(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? self::delete("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    }
}
