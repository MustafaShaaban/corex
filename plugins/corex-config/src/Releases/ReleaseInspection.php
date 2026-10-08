<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * What a package turned out to be, once it had been inspected and not refused: what the screen
 * states before anything on the site changes (spec 107, FR-010, FR-015).
 */
final readonly class ReleaseInspection
{
    /**
     * @param ReleaseManifest $manifest          What the package says it is.
     * @param string|null     $replaces          The version the site is running, where it knows.
     * @param bool            $older             The package is an older release than the one running: installing it needs saying twice.
     * @param bool            $clientUnconfirmed The site cannot say which client it is, so nothing was compared: the administrator confirms the package's client by name.
     * @param int             $files             Files in the folders the release owns.
     * @param int             $bytes             Their size.
     */
    public function __construct(
        public ReleaseManifest $manifest,
        public ?string $replaces,
        public bool $older,
        public bool $clientUnconfirmed,
        public int $files,
        public int $bytes,
    ) {
    }
}
