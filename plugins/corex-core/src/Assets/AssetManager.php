<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

use Closure;
use Corex\Multisite\SiteScoped;

/**
 * Resolves an asset's URL, filesystem path, and cache-busting version for a given base
 * (spec 047) — `url('images/logo.svg')`, `path(...)`, `version('build/app.css')`. Reads a
 * build manifest (hashed filename + hash) when present, else the plain file; the version
 * follows the environment (filemtime in local, manifest hash in production, framework/site
 * version fallback). The base URL is supplied pre-normalised either as a string or as a lazy,
 * site-aware resolver. Only that URL is memoized and invalidated on a blog switch; the manifest
 * and fallback version remain file-derived and unchanged.
 */
final class AssetManager implements SiteScoped
{
    private ?string $baseUrl = null;

    /**
     * @param Closure():string|string $baseUrlResolver
     */
    public function __construct(
        private readonly string $baseDir,
        private readonly Closure|string $baseUrlResolver,
        private readonly AssetEnvironment $environment,
        private readonly BuildManifest $manifest,
        private readonly string $fallbackVersion,
        private readonly AssetVersion $versioner,
    ) {
    }

    public function url(string $relative): string
    {
        return rtrim($this->baseUrl(), '/') . '/' . ltrim($this->outputFile($relative), '/');
    }

    public function path(string $relative): string
    {
        return rtrim($this->baseDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($this->outputFile($relative), '/');
    }

    public function version(string $relative): string
    {
        $entry = $this->manifest->lookup($relative);
        $hash  = $entry['hash'] ?? null;
        $file  = $entry['file'] ?? $relative;

        $path  = rtrim($this->baseDir, '/\\') . DIRECTORY_SEPARATOR . ltrim($file, '/');
        $mtime = is_file($path) ? (filemtime($path) ?: null) : null;

        return $this->versioner->token($relative, $mtime, $hash, $this->environment, $this->fallbackVersion);
    }

    public function environment(): AssetEnvironment
    {
        return $this->environment;
    }

    public function forgetSiteState(int $siteId): void
    {
        $this->baseUrl = null;
    }

    private function baseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->baseUrl = $this->baseUrlResolver instanceof Closure
                ? ($this->baseUrlResolver)()
                : $this->baseUrlResolver;
        }

        return $this->baseUrl;
    }

    /**
     * The output filename for a source — the manifest's hashed file when present, else the
     * source path as given.
     */
    private function outputFile(string $relative): string
    {
        $entry = $this->manifest->lookup($relative);

        return $entry !== null ? $entry['file'] : $relative;
    }
}
