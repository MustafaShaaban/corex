<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Enforces the client/framework boundary (spec 050, reusing the spec-049 boundary): given a
 * list of changed files, it **fails** if any is under a forbidden Corex framework path —
 * naming the offenders — and **passes** client plugin/theme/docs/specs changes. Matching is
 * by **path prefix** (never substring), with an explicit override for an approved framework
 * change. Pure (the changed-file list + forbidden prefixes are injected); reading the diff is
 * a thin boundary.
 */
final class ComplianceCheck
{
    /**
     * @param list<string> $changedFiles
     * @param list<string> $forbiddenPrefixes the framework path prefixes
     *
     * @return array{passed:bool,violations:list<string>}
     */
    public function evaluate(array $changedFiles, array $forbiddenPrefixes, bool $allowFramework = false): array
    {
        if ($allowFramework) {
            return ['passed' => true, 'violations' => []];
        }

        $violations = [];

        foreach ($changedFiles as $file) {
            if ($this->isForbidden($file, $forbiddenPrefixes)) {
                $violations[] = $file;
            }
        }

        return ['passed' => $violations === [], 'violations' => $violations];
    }

    /**
     * @param list<string> $forbiddenPrefixes
     */
    private function isForbidden(string $file, array $forbiddenPrefixes): bool
    {
        $normalised = ltrim($file, '/');

        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($normalised, ltrim($prefix, '/'))) {
                return true;
            }
        }

        return false;
    }
}
