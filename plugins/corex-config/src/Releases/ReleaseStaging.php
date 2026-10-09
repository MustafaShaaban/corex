<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use ZipArchive;

/**
 * A package unpacked beside the site, and held to its own description (spec 107, plan D8: the
 * unpack and verify steps; FR-013).
 *
 * Only the folders a release owns are unpacked, into `staging/` in the installer's own place.
 * Nothing of the site is touched: a folder is moved into the site only after every one of them
 * has been found to be what the package says it holds. A package also holds WordPress itself,
 * which an installation does not replace and this does not unpack.
 *
 * A host cuts a request off after some seconds, and a release is thousands of files. So a
 * request unpacks for a while and answers where it has got to, and the next goes on from there.
 */
final class ReleaseStaging
{
    public const DIFFERS = 'differs_from_description';

    /** How long a request unpacks before it answers: well inside the thirty seconds a host commonly allows. */
    private const SECONDS = 8.0;

    public function __construct(
        private readonly ReleaseStore $store,
        private readonly ReleaseContentHash $contents,
        private readonly float $seconds = self::SECONDS,
    ) {
    }

    /**
     * Unpack more of the package, from the entry given.
     *
     * At least one entry is dealt with, however little time there is, so every request gets
     * somewhere. From the first entry, whatever an earlier attempt left is removed first.
     *
     * @param int $cursor The entry to go on from: 0 to begin, then what the last request answered.
     *
     * @return array{cursor:int,of:int} Where the next request starts, and how many entries there are: equal when it is all unpacked.
     *
     * @throws ReleaseRefused When the file cannot be opened, or an entry would be written outside its folder. Nothing staged is kept.
     */
    public function unpack(string $zipFile, ReleaseManifest $manifest, int $cursor): array
    {
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::RDONLY) !== true) {
            throw new ReleaseRefused(
                ReleaseRefused::NOT_A_ZIP,
                __('The package could not be opened to be unpacked. Send it again.', 'corex'),
            );
        }

        if ($cursor === 0) {
            $this->clear();
        }

        $until = microtime(true) + $this->seconds;

        try {
            do {
                $this->stage($zip, $manifest, $cursor);
                $cursor++;
            } while ($cursor < $zip->numFiles && microtime(true) < $until);

            return ['cursor' => $cursor, 'of' => $zip->numFiles];
        } finally {
            $zip->close();
        }
    }

    /**
     * Hold one unpacked folder to what the package says it holds.
     *
     * @throws ReleaseRefused When it is not that. Nothing staged is kept: a package that is wrong in one folder is not installed in the others.
     */
    public function verify(ReleaseManifest $manifest, string $releasePath): void
    {
        $said  = $manifest->contentsOf($releasePath);
        $found = $this->contents->describe($this->folderOf($releasePath));

        if ($found === $said) {
            return;
        }

        $this->clear();

        throw new ReleaseRefused(self::DIFFERS, sprintf(
            /* translators: 1: a folder of the release. 2: files found. 3: bytes found. 4: files the package says. 5: bytes the package says. */
            __('What was unpacked for %1$s is not what the package says it holds: %2$d files and %3$d bytes, where it says %4$d and %5$d, or the same with a file changed. Nothing on the site was changed. Send the package again.', 'corex'),
            $releasePath,
            $found['files'],
            $found['bytes'],
            $said['files'],
            $said['bytes'],
        ));
    }

    /** Where a folder of the release is while it is staged. */
    public function folderOf(string $releasePath): string
    {
        return $this->store->folder('staging') . '/' . $releasePath;
    }

    /** Remove everything that is staged. */
    public function clear(): void
    {
        $this->store->empty('staging');
    }

    /**
     * Write one entry of the package where it is staged, if a release owns it.
     *
     * @throws ReleaseRefused For an entry whose path leaves the folder it is in.
     */
    private function stage(ZipArchive $zip, ReleaseManifest $manifest, int $index): void
    {
        $name = (string) $zip->getNameIndex($index);

        if (! $this->isTheReleases($name, $manifest)) {
            return;
        }

        // The inspection refuses such a package before this is asked for. This does not rely
        // on having been asked second: it is the one that writes.
        if (ReleaseEntryName::leavesThePackage($name)) {
            $this->clear();

            throw new ReleaseRefused(ReleaseRefused::UNSAFE_ENTRY, sprintf(
                /* translators: %s: the path of a file inside the package. */
                __('This package holds a file whose path leaves the package (%s). It was not unpacked.', 'corex'),
                $name,
            ));
        }

        $target = $this->store->folder('staging') . '/' . $name;

        if (str_ends_with($name, '/')) {
            wp_mkdir_p($target);

            return;
        }

        wp_mkdir_p(dirname($target));
        $this->copy($zip, $name, $target);
    }

    private function isTheReleases(string $name, ReleaseManifest $manifest): bool
    {
        foreach ($manifest->releasePaths as $path) {
            if (str_starts_with($name, $path . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ReleaseRefused When the host will not let the file be written.
     */
    private function copy(ZipArchive $zip, string $name, string $target): void
    {
        $from = $zip->getStream($name);
        // Direct writes, not WP_Filesystem: it can ask for FTP credentials it has no way to
        // ask for in the middle of an installation. The path is under the installer's folder.
        $to = $from === false ? false : fopen($target, 'wb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

        if ($from === false || $to === false) {
            $this->clear();

            throw new ReleaseRefused(ReleaseHostFacts::NOT_WRITABLE, sprintf(
                /* translators: %s: the path of a file inside the package. */
                __('A file of the package could not be unpacked (%s). Nothing on the site was changed. Check the site can write in wp-content/corex-releases, and send the package again.', 'corex'),
                $name,
            ));
        }

        stream_copy_to_stream($from, $to);
        fclose($from); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($to); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    }
}
