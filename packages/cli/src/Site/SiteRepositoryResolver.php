<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * Decides, for the directory a site is about to be generated in, what make:site may write outside
 * it (spec 102, FR-005). The answer is the repository root only when the site sits directly under
 * that repository's `sites/` — the one layout in which the client workflow has a place to go.
 * Anywhere else the root is withheld, and the command says the workflow was not generated rather
 * than guessing a location for it.
 */
final class SiteRepositoryResolver
{
    private const SITES_DIRECTORY = 'sites';

    public function __construct(
        private readonly FrameworkBaselineSource $baselines,
        private readonly string $repositoryRoot,
    ) {
    }

    /**
     * @param string $siteDir          The site root, absolute or relative.
     * @param string $workingDirectory What a relative site root is relative to.
     */
    public function for(string $siteDir, string $workingDirectory = ''): SiteRepository
    {
        $root    = $this->normalize($this->repositoryRoot);
        $site    = $this->absolute($this->normalize($siteDir), $this->normalize($workingDirectory));
        $inSites = dirname($site) === $root . '/' . self::SITES_DIRECTORY;

        return new SiteRepository($this->baselines->current(), $inSites ? $root : null);
    }

    private function absolute(string $path, string $workingDirectory): string
    {
        if (preg_match('#^(/|[A-Za-z]:/)#', $path) === 1) {
            return $path;
        }

        return $workingDirectory . '/' . (string) preg_replace('#^\./#', '', $path);
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
