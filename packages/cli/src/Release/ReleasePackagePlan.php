<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Plans a Corex release package (spec 050): which framework paths the update ZIP includes
 * (the `corex-*` plugins + theme), what it excludes (tests, specs, node_modules, dev vendor,
 * client/app code, secrets), and the spec-034 `manifest.json` the self-update mechanism reads.
 * Pure: `includes()` decides per path from rules; `manifest()` builds the document — both
 * unit-tested. Writing the ZIP/manifest is a thin boundary. No secret, no client file.
 */
final class ReleasePackagePlan
{
    /**
     * @param list<string> $frameworkPaths  path prefixes to include (the framework)
     * @param list<string> $excludePatterns substrings that force exclusion
     */
    public function __construct(
        private readonly array $frameworkPaths,
        private readonly array $excludePatterns,
    ) {
    }

    public function includes(string $path): bool
    {
        $normalised = ltrim($path, '/');

        foreach ($this->excludePatterns as $pattern) {
            if (str_contains($normalised, $pattern)) {
                return false;
            }
        }

        foreach ($this->frameworkPaths as $prefix) {
            if (str_starts_with($normalised, ltrim($prefix, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed> the spec-034 manifest
     */
    public function manifest(string $version, string $downloadUrl, string $changelog): array
    {
        return [
            'version'      => $version,
            'requires'     => '7.0',
            'requires_php' => '8.3',
            'tested'       => '7.0',
            'download_url' => $downloadUrl,
            'sections'     => [
                'description' => 'Corex framework update.',
                'changelog'   => $changelog,
            ],
        ];
    }
}
