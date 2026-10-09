<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

/**
 * The release a site is running, as far as the site can say (spec 107, plan D4).
 *
 * A site that has installed a release from the admin recorded its description when it did. One
 * that has only ever been unpacked by hand has the description the package left in its root.
 * One with neither does not know which release it is, or whose: a checkout, or a site older
 * than descriptions. That is a thing to say, not to guess at, because which client a site is
 * decides which packages it may be given.
 */
final class InstalledRelease
{
    /** Where an installation from the admin records the description of what it installed. */
    public const OPTION = 'corex_release_installed';

    private const DESCRIPTION = 'corex-release.json';

    /**
     * @param string $siteRoot The folder WordPress is in, where a package unpacked by hand leaves its description.
     */
    public function __construct(private readonly string $siteRoot)
    {
    }

    public function isKnown(): bool
    {
        return $this->described() !== null;
    }

    public function version(): ?string
    {
        $described = $this->described();

        return $described === null ? null : (string) $described['corex_version'];
    }

    /**
     * The client the release was built for; null for the framework alone, and for a site that
     * does not know. `isKnown()` tells the two apart.
     */
    public function client(): ?string
    {
        $client = $this->described()['client'] ?? null;

        return is_string($client) && $client !== '' ? $client : null;
    }

    /**
     * The folders the running release said it holds. An installation moves one of these out
     * when the next release does not hold it. None, for a release that did not say or a site
     * that does not know: nothing is then moved but what the next release replaces.
     *
     * @return list<string>
     */
    public function ownedFolders(): array
    {
        $paths = $this->described()['release_paths'] ?? [];

        return array_values(array_filter(
            is_array($paths) ? $paths : [],
            static fn (mixed $path): bool => is_string($path) && ReleaseManifest::owns($path),
        ));
    }

    /**
     * @return array<string,mixed>|null The description, or null when the site has none it can rely on.
     */
    private function described(): ?array
    {
        $recorded = get_option(self::OPTION);

        if (self::isDescription($recorded)) {
            return $recorded;
        }

        $file = rtrim($this->siteRoot, '/\\') . '/' . self::DESCRIPTION;

        if (! is_file($file)) {
            return null;
        }

        $left = json_decode((string) file_get_contents($file), true);

        return self::isDescription($left) ? $left : null;
    }

    /**
     * @phpstan-assert-if-true array<string,mixed> $candidate
     */
    private static function isDescription(mixed $candidate): bool
    {
        return is_array($candidate) && is_string($candidate['corex_version'] ?? null) && $candidate['corex_version'] !== '';
    }
}
