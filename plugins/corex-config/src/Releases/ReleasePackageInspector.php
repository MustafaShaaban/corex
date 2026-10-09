<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use ZipArchive;

/**
 * Reads a release package where it landed and refuses it if it is wrong, before anything of it
 * is unpacked (spec 107, US2; plan D3).
 *
 * In order, stopping at the first refusal: it is a zip; its description is at its top and can be
 * relied on; it is for this site's client; this host can run it; it holds every folder it says
 * it does; and no entry in it would be written outside the package, or is a thing a release
 * never holds.
 *
 * It reads. It writes nothing, anywhere: a refusal leaves the site exactly as it was (FR-016),
 * because there is nothing here that could have changed it.
 */
final class ReleasePackageInspector
{
    /**
     * Path parts a package must never hold, compared without regard to case.
     *
     * The builder refuses them when it makes a package (`FORBIDDEN_SEGMENTS` in
     * `scripts/build-shared-host-dist.mjs`). A site does not take a package's word for how it
     * was built, so the list is here too, and a test on each side holds the two to one file.
     */
    public const FORBIDDEN_SEGMENTS = [
        '.git',
        '.github',
        'node_modules',
        'tests',
        '__tests__',
        '.claude',
        '.agents',
        '.specify',
        '.env',
        'wp-config.php',
        'debug.log',
        '.DS_Store',
    ];

    private const DESCRIPTION = 'corex-release.json';

    /**
     * @param string $phpVersion       The PHP this host runs.
     * @param string $wordPressVersion The WordPress this site runs.
     */
    public function __construct(
        private readonly InstalledRelease $installed,
        private readonly string $phpVersion,
        private readonly string $wordPressVersion,
    ) {
    }

    /**
     * @throws ReleaseRefused When the package is not one this site should install.
     */
    public function inspect(string $zipFile): ReleaseInspection
    {
        $zip = $this->open($zipFile);

        try {
            $manifest = ReleaseManifest::fromJson($this->description($zip));

            $this->refuseAPackageForSomebodyElse($manifest);
            $this->refuseWhatThisHostCannotRun($manifest);
            $this->refuseWhatIsNotSafeToUnpack($zip, $manifest);
        } finally {
            $zip->close();
        }

        $running = $this->installed->version();

        return new ReleaseInspection(
            $manifest,
            $running,
            $running !== null && version_compare($manifest->version, $running, '<'),
            ! $this->installed->isKnown(),
            ...$this->measure($manifest),
        );
    }

    private function open(string $zipFile): ZipArchive
    {
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::RDONLY) !== true) {
            throw new ReleaseRefused(
                ReleaseRefused::NOT_A_ZIP,
                __('This file is not a zip that can be opened. Build the package again with --zip and give the site that file.', 'corex'),
            );
        }

        return $zip;
    }

    private function description(ZipArchive $zip): string
    {
        $description = $zip->getFromName(self::DESCRIPTION);

        if ($description !== false) {
            return $description;
        }

        throw new ReleaseRefused(
            ReleaseRefused::NOT_A_PACKAGE,
            $this->holdsThePackageInAFolder($zip)
                ? __('This zip holds a folder that holds the package. Zip what is inside the folder, not the folder, or build the package with --zip.', 'corex')
                : __('This is not a CoreX release package: there is no corex-release.json at its top.', 'corex'),
        );
    }

    /**
     * The commonest wrong package: `dist/` itself, zipped by hand, so that everything is one
     * folder down from where a site looks for it.
     */
    private function holdsThePackageInAFolder(ZipArchive $zip): bool
    {
        foreach ($this->entryNames($zip) as $name) {
            if (preg_match('#^[^/]+/' . preg_quote(self::DESCRIPTION, '#') . '$#', $name) === 1) {
                return true;
            }
        }

        return false;
    }

    private function refuseAPackageForSomebodyElse(ReleaseManifest $manifest): void
    {
        if (! $this->installed->isKnown() || $manifest->client === $this->installed->client()) {
            return;
        }

        $site = $this->installed->client();

        throw new ReleaseRefused(ReleaseRefused::OTHER_CLIENT, match (true) {
            $manifest->client === null => sprintf(
                /* translators: %s: a client's name. */
                __('This package is the framework alone, and this site is %s. It would remove the site\'s own code.', 'corex'),
                $site,
            ),
            $site === null => sprintf(
                /* translators: %s: a client's name. */
                __('This package is for %s, and this site is the framework alone.', 'corex'),
                $manifest->client,
            ),
            default => sprintf(
                /* translators: 1: the client a package was built for. 2: the client this site belongs to. */
                __('This package is for %1$s, and this site is %2$s.', 'corex'),
                $manifest->client,
                $site,
            ),
        });
    }

    private function refuseWhatThisHostCannotRun(ReleaseManifest $manifest): void
    {
        if (version_compare($this->phpVersion, $manifest->requiresPhp, '<')) {
            throw new ReleaseRefused(ReleaseRefused::NEEDS_PHP, sprintf(
                /* translators: 1: the PHP version a release needs. 2: the PHP version this host runs. */
                __('This release needs PHP %1$s or newer, and this host runs %2$s.', 'corex'),
                $manifest->requiresPhp,
                $this->phpVersion,
            ));
        }

        if (version_compare($this->wordPressVersion, $manifest->requiresWordPress, '<')) {
            throw new ReleaseRefused(ReleaseRefused::NEEDS_WORDPRESS, sprintf(
                /* translators: 1: the WordPress version a release needs. 2: the WordPress version this site runs. */
                __('This release needs WordPress %1$s or newer, and this site runs %2$s.', 'corex'),
                $manifest->requiresWordPress,
                $this->wordPressVersion,
            ));
        }
    }

    private function refuseWhatIsNotSafeToUnpack(ZipArchive $zip, ReleaseManifest $manifest): void
    {
        $missing = array_fill_keys($manifest->releasePaths, true);

        foreach ($this->entryNames($zip) as $name) {
            $this->refuseAnEntryThatLeavesThePackage($name);
            $this->refuseAnEntryAReleaseNeverHolds($name);

            foreach ($manifest->releasePaths as $path) {
                if (str_starts_with($name, $path . '/')) {
                    unset($missing[$path]);
                }
            }
        }

        if ($missing !== []) {
            throw new ReleaseRefused(ReleaseRefused::MISSING_FOLDER, sprintf(
                /* translators: %s: a folder path from the package. */
                __('This package says it holds %s, and does not. Build it again.', 'corex'),
                (string) array_key_first($missing),
            ));
        }
    }

    private function refuseAnEntryThatLeavesThePackage(string $name): void
    {
        if (ReleaseEntryName::leavesThePackage($name)) {
            throw new ReleaseRefused(ReleaseRefused::UNSAFE_ENTRY, sprintf(
                /* translators: %s: the path of a file inside the package. */
                __('This package holds a file whose path leaves the package (%s). It was not unpacked.', 'corex'),
                $name,
            ));
        }
    }

    private function refuseAnEntryAReleaseNeverHolds(string $name): void
    {
        $forbidden = array_map('strtolower', self::FORBIDDEN_SEGMENTS);

        foreach (explode('/', strtolower($name)) as $segment) {
            if (in_array($segment, $forbidden, true)) {
                throw new ReleaseRefused(ReleaseRefused::FORBIDDEN_ENTRY, sprintf(
                    /* translators: %s: the path of a file inside the package. */
                    __('This package holds a file a release never holds (%s). It was not unpacked.', 'corex'),
                    $name,
                ));
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function entryNames(ZipArchive $zip): iterable
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            yield (string) $zip->getNameIndex($index);
        }
    }

    /**
     * @return array{files:int,bytes:int}
     */
    private function measure(ReleaseManifest $manifest): array
    {
        $files = 0;
        $bytes = 0;

        foreach ($manifest->releasePaths as $path) {
            $holds  = $manifest->contentsOf($path);
            $files += $holds['files'];
            $bytes += $holds['bytes'];
        }

        return ['files' => $files, 'bytes' => $bytes];
    }
}
