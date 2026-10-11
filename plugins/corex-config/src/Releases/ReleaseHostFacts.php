<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use ZipArchive;

/**
 * What this host can and cannot do towards installing a release, said when the screen is opened
 * and not when an installation is half done (spec 107, FR-017; plan D15).
 *
 * The facts are read in one place (`ofThisHost()`) and judged in another (`blockers()`), so the
 * judging can be held to account without a host that is wrong in each of the ways one can be.
 */
final readonly class ReleaseHostFacts
{
    public const FILE_CHANGES_OFF = 'file_changes_off';
    public const NO_ZIP           = 'no_zip';
    public const NOT_WRITABLE     = 'not_writable';
    public const NO_SPACE         = 'no_space';
    public const LAYOUT_DIFFERS   = 'layout_differs';

    /**
     * The constant a site's configuration file defines as true to say releases may be
     * installed from the admin although the site is set not to change its own files (plan D16).
     */
    public const ALLOWED_BY = 'COREX_ALLOW_RELEASE_INSTALL';

    /**
     * @param bool         $fileChangesAllowed Whether the site is set to let its own files be changed.
     * @param bool         $canOpenZips        Whether PHP has what opens a zip.
     * @param list<string> $unwritable         The folders an installation writes in that cannot be written.
     * @param int|null     $freeBytes          The room where releases are kept; null on a host that will not say.
     * @param bool         $laidOutAsAPackage  Whether the site's plugins and themes are where a package holds them:
     *                                         `wp-content/plugins` and `wp-content/themes`. A release's folders are
     *                                         moved to those paths and no others.
     */
    public function __construct(
        public bool $fileChangesAllowed,
        public bool $canOpenZips,
        public array $unwritable,
        public ?int $freeBytes,
        public bool $laidOutAsAPackage = true,
    ) {
    }

    /**
     * @param list<string> $folders                The folders an installation writes in; the first is where releases are kept.
     * @param bool         $allowedByConfiguration Whether the site's configuration file says releases may be installed
     *                                             (`ALLOWED_BY`). It allows a release and nothing else: WordPress is
     *                                             not asked to let anything through on the strength of it.
     * @param bool         $laidOutAsAPackage      Whether the site's plugins and themes are where a package holds them.
     */
    public static function ofThisHost(array $folders, bool $allowedByConfiguration = false, bool $laidOutAsAPackage = true): self
    {
        return new self(
            wp_is_file_mod_allowed('corex_release_install') || $allowedByConfiguration,
            class_exists(ZipArchive::class),
            array_values(array_filter($folders, static fn (string $folder): bool => ! self::canWriteIn($folder))),
            self::roomIn($folders[0] ?? ''),
            $laidOutAsAPackage,
        );
    }

    /**
     * A folder that is not there yet is one an installation makes (a site may have no must-use
     * plugins folder): what decides it is the folder above. Asked of the missing folder itself,
     * WordPress answers differently on Windows and elsewhere.
     */
    private static function canWriteIn(string $folder): bool
    {
        return wp_is_writable(is_dir($folder) ? $folder : dirname($folder));
    }

    /**
     * Everything that prevents an installation, each with what to do about it.
     *
     * @param int $neededBytes The room an installation of the package in hand takes.
     *
     * @return list<array{reason:string,message:string}>
     */
    public function blockers(int $neededBytes): array
    {
        $blockers = [];

        if (! $this->fileChangesAllowed) {
            $blockers[] = [
                'reason'  => self::FILE_CHANGES_OFF,
                'message' => sprintf(
                    /* translators: %s: a line of PHP to add to wp-config.php. */
                    __('This site is set not to change its own files (DISALLOW_FILE_MODS, or a host that has turned updates off). To install releases from this screen and keep that setting, add this line to wp-config.php: %s It allows a release to be installed here and nothing else: WordPress\'s own installers, updaters and editors stay off.', 'corex'),
                    "define( '" . self::ALLOWED_BY . "', true );",
                ),
            ];
        }

        if (! $this->laidOutAsAPackage) {
            $blockers[] = [
                'reason'  => self::LAYOUT_DIFFERS,
                'message' => __('This site keeps its plugins or its themes somewhere other than a release package holds them (wp-content/plugins and wp-content/themes, in a folder named wp-content). A release cannot be installed from here: its folders would be put where WordPress does not look.', 'corex'),
            ];
        }

        if (! $this->canOpenZips) {
            $blockers[] = [
                'reason'  => self::NO_ZIP,
                'message' => __('This host\'s PHP cannot open a zip. Ask the host to turn on the zip extension.', 'corex'),
            ];
        }

        foreach ($this->unwritable as $folder) {
            $blockers[] = [
                'reason'  => self::NOT_WRITABLE,
                'message' => sprintf(
                    /* translators: %s: a folder's path on the server. */
                    __('This folder cannot be written by the site, and an installation replaces what is in it: %s', 'corex'),
                    $folder,
                ),
            ];
        }

        if ($this->freeBytes !== null && $this->freeBytes < $neededBytes) {
            $blockers[] = [
                'reason'  => self::NO_SPACE,
                'message' => sprintf(
                    /* translators: 1: the disk space an installation needs, e.g. "90 MB". 2: the space the host has free. */
                    __('An installation of this release needs %1$s free, and the host has %2$s.', 'corex'),
                    size_format($neededBytes),
                    size_format($this->freeBytes),
                ),
            ];
        }

        return $blockers;
    }

    /**
     * Shared hosts often switch `disk_free_space()` off, and some answer false for a folder
     * they will not measure. Neither is "no room": it is a host that does not say.
     */
    private static function roomIn(string $folder): ?int
    {
        if ($folder === '' || ! function_exists('disk_free_space')) {
            return null;
        }

        $free = @disk_free_space($folder); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a host that forbids it warns as well as failing.

        return is_float($free) ? (int) $free : null;
    }
}
