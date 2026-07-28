<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

use InvalidArgumentException;

/**
 * Computes how to stamp a target version across the framework — purely. Given a valid semver and a
 * map of `path => contents`, it rewrites three things in each file: the first header `Version:` line
 * (plugin/theme headers), every `COREX_*_VERSION` constant, and the documentation site's exported
 * `CURRENT_VERSION`. It returns only the files whose contents actually change, so applying the plan
 * is idempotent and a dry-run is exact. The CLI `VersionCommand` reads the files, calls {@see
 * plan()}, and writes (or previews) the results.
 */
final class VersionPlan
{
    private const HEADER_PATTERN   = '/^(\s*(?:\*\s*)?Version:\s*)\S.*$/m';
    private const CONSTANT_PATTERN = "/(COREX_[A-Z0-9_]*VERSION'\s*,\s*')[^']*(')/";

    /**
     * The docs site states the framework version in TypeScript rather than a PHP header, so it needs
     * its own pattern. Without it the docs published alongside a release advertise the previous
     * version, and nothing catches it — v0.35.0 shipped that way and was corrected by hand.
     */
    private const TS_EXPORT_PATTERN = "/(export const CURRENT_VERSION\s*=\s*')[^']*(')/";

    /** The npm workspace root. Anchored to the top-level key so no dependency's version is touched. */
    private const PACKAGE_JSON_PATTERN = '/^(\s*"version":\s*")[^"]*(",?)$/m';

    /**
     * The places prose states the current version.
     *
     * These were stamped by hand at every release until 0.39.0, which is why the roadmap could sit
     * three releases behind a README that was correct — the command's own docblock promised that
     * none of these drifts from the release tag, and four of them did.
     *
     * Each pattern is anchored to the exact sentence that declares the *current* version, never to a
     * bare version string. `ROADMAP.md` and `CHANGELOG.md` are full of historical references —
     * "released as v0.38.1" is a true statement about the past and rewriting it would corrupt the
     * record rather than update it.
     *
     * @var list<string>
     */
    private const PROSE_PATTERNS = [
        // README.md — the status heading and the readiness command it tells you to run.
        '/^(## Status: v)\d+\.\d+\.\d+(,)/m',
        '/^(wp corex readiness )\d+\.\d+\.\d+$/m',
        // ROADMAP.md
        '/^(\*\*Currently released: v)\d+\.\d+\.\d+(\.\*\*)/m',
        // PROJECT-STATUS.md and its docs-site mirror.
        '/^(\*\*Version )\d+\.\d+\.\d+(\*\* ·)/m',
        '/^(\*\*Version )\d+\.\d+\.\d+(\.\*\*)/m',
    ];

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
        $contents = preg_replace(self::CONSTANT_PATTERN, '${1}' . $version . '${2}', $contents) ?? $contents;
        $contents = preg_replace(self::TS_EXPORT_PATTERN, '${1}' . $version . '${2}', $contents) ?? $contents;
        $contents = preg_replace(self::PACKAGE_JSON_PATTERN, '${1}' . $version . '${2}', $contents, 1) ?? $contents;

        foreach (self::PROSE_PATTERNS as $pattern) {
            $contents = preg_replace($pattern, '${1}' . $version . '${2}', $contents) ?? $contents;
        }

        return $contents;
    }
}
