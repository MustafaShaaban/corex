<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

/**
 * Computes how to stamp a target version across the framework — purely. Given a valid semver and a
 * map of `path => contents`, it rewrites two things in each file: the first header `Version:` line
 * (plugin/theme headers) and every `COREX_*_VERSION` constant. It returns only the files whose
 * contents actually change, so applying the plan is idempotent and a dry-run is exact. The CLI
 * `VersionCommand` reads the files, calls {@see plan()}, and writes (or previews) the results.
 */
final class VersionPlan
{
    private const HEADER_PATTERN   = '/^(\s*(?:\*\s*)?Version:\s*)\S.*$/m';
    private const CONSTANT_PATTERN = "/(COREX_[A-Z0-9_]*VERSION'\s*,\s*')[^']*(')/";

    /**
     * A semver `x.y.z`, optionally with a `-prerelease` and/or `+build` suffix.
     */
    public static function isValid(string $version): bool
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version);
    }

    /**
     * @param array<string,string> $files path => current contents
     *
     * @return array<string,string> path => new contents, for changed files only
     */
    public function plan(string $version, array $files): array
    {
        if (! self::isValid($version)) {
            throw new InvalidArgumentException(sprintf('Invalid version "%s" (expected semver x.y.z).', $version));
        }

        $changed = [];

        foreach ($files as $path => $contents) {
            $next = $this->stamp($version, $contents);

            if ($next !== $contents) {
                $changed[$path] = $next;
            }
        }

        return $changed;
    }

    private function stamp(string $version, string $contents): string
    {
        $contents = preg_replace(self::HEADER_PATTERN, '${1}' . $version, $contents, 1) ?? $contents;

        return preg_replace(self::CONSTANT_PATTERN, '${1}' . $version . '${2}', $contents) ?? $contents;
    }
}
