<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use ZipArchive;

/**
 * The plugins and themes a package would put back to an older version than the site has (spec
 * 107, FR-029).
 *
 * A release replaces each of its folders whole, so a plugin updated in the admin after the
 * package was built would go back to the copy in the package without anybody being told. This
 * reads the version each folder's header gives, on the site and in the package, and names every
 * folder where the package's is the lower.
 *
 * A folder whose version is the release's own is one of CoreX's: going back on those is going
 * back to an older release, which FR-015 asks about. A folder that is not on the site, or whose
 * version cannot be read on either side, is not compared.
 *
 * It reads. It writes nothing.
 */
final class ReleaseDowngrades
{
    /**
     * @param string $siteRoot The folder a release's paths are relative to.
     */
    public function __construct(private readonly string $siteRoot)
    {
    }

    /**
     * @return list<array{path:string,name:string,site:string,package:string}> Each folder the package holds an older version of, with the two versions.
     */
    public function in(ZipArchive $zip, ReleaseManifest $manifest): array
    {
        $topFiles   = $this->topFilesIn($zip, $manifest->releasePaths);
        $downgrades = [];

        foreach ($manifest->releasePaths as $path) {
            $packaged = ReleaseFolderVersion::of(
                $path,
                $topFiles[$path],
                static fn (string $file): ?string => self::text($zip->getFromName($path . '/' . $file, ReleaseFolderVersion::HEAD_BYTES)),
            );

            if ($packaged === null || $packaged->version === $manifest->version) {
                continue;
            }

            $live = $this->onTheSite($path);

            if ($live !== null && version_compare($packaged->version, $live->version, '<')) {
                $downgrades[] = ['path' => $path, 'name' => $live->name, 'site' => $live->version, 'package' => $packaged->version];
            }
        }

        return $downgrades;
    }

    /**
     * The names of the files directly in each release path of the package.
     *
     * @param list<string> $paths
     *
     * @return array<string,list<string>>
     */
    private function topFilesIn(ZipArchive $zip, array $paths): array
    {
        $topFiles = array_fill_keys($paths, []);

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name   = (string) $zip->getNameIndex($index);
            $folder = dirname($name);

            if (isset($topFiles[$folder]) && ! str_ends_with($name, '/')) {
                $topFiles[$folder][] = basename($name);
            }
        }

        return $topFiles;
    }

    private function onTheSite(string $path): ?ReleaseFolderVersion
    {
        $folder = rtrim($this->siteRoot, '/\\') . '/' . $path;

        if (! is_dir($folder)) {
            return null;
        }

        return ReleaseFolderVersion::of(
            $path,
            array_values(array_filter(scandir($folder) ?: [], static fn (string $file): bool => is_file($folder . '/' . $file))),
            static fn (string $file): ?string => self::text(file_get_contents($folder . '/' . $file, false, null, 0, ReleaseFolderVersion::HEAD_BYTES)),
        );
    }

    private static function text(string|false $read): ?string
    {
        return $read === false ? null : $read;
    }
}
