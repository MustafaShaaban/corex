<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Builds CI/security readiness findings from repository-owned governance files.
 */
final class CiSecurityReadiness
{
    /**
     * @var list<string>
     */
    private const REPO_FILE_CONTROLS = [
        '.github/workflows/ci.yml' => 'CI workflow',
        '.github/workflows/e2e.yml' => 'E2E workflow',
        '.github/workflows/docs.yml' => 'docs workflow',
        'SECURITY.md' => 'security policy',
        'CONTRIBUTING.md' => 'contributing guide',
        '.github/CODEOWNERS' => 'CODEOWNERS',
        '.github/dependabot.yml' => 'Dependabot',
        '.github/workflows/codeql.yml' => 'CodeQL',
    ];

    /**
     * @param array<string,string> $files path => contents
     *
     * @return list<ReadinessFinding>
     */
    public function evaluate(array $files): array
    {
        return [
            $this->repoFileFinding($files),
            new ReadinessFinding(
                'ci-security',
                ReadinessFinding::STATUS_ENVIRONMENT_GATED,
                'GitHub settings controls require repository settings verification',
                ['github-settings:branch-protection', 'github-settings:required-checks', 'github-settings:secret-scanning'],
                'repo-settings',
                false,
                'Verify branch protection, required checks, and secret scanning in GitHub repository settings.',
            ),
        ];
    }

    /**
     * @param array<string,string> $files
     */
    private function repoFileFinding(array $files): ReadinessFinding
    {
        $evidence = [];
        $missing = [];

        foreach (self::REPO_FILE_CONTROLS as $path => $label) {
            if (array_key_exists($path, $files)) {
                $evidence[] = $path;
            } else {
                $missing[] = 'missing:' . $path;
            }
        }

        $status = $missing === []
            ? ReadinessFinding::STATUS_PASS
            : ReadinessFinding::STATUS_WARNING;

        return new ReadinessFinding(
            'ci-security',
            $status,
            'CI/security repo-file controls checked',
            [...$evidence, ...$missing],
            'repo-settings',
            false,
            $missing === []
                ? 'None'
                : sprintf('Add missing repo-file controls: %s.', $this->missingLabels($missing)),
        );
    }

    /**
     * @param list<string> $missing
     */
    private function missingLabels(array $missing): string
    {
        $labels = [];

        foreach ($missing as $path) {
            $path = substr($path, strlen('missing:'));
            $labels[] = self::REPO_FILE_CONTROLS[$path] ?? $path;
        }

        return implode(', ', $labels);
    }
}
