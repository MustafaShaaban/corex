<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

use Corex\Cli\Release\ReadinessFinding;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

defined('ABSPATH') || exit;

/**
 * Validates generated make:site output for client-site readiness.
 */
final class SiteScaffoldValidator
{
    /**
     * @var list<string>
     */
    private const GOVERNANCE_FILES = [
        'AGENTS.md',
        'CLAUDE.md',
        'PROGRESS.md',
        'DECISIONS.md',
        'specs/.gitkeep',
        'docs/.gitkeep',
    ];

    /**
     * @var list<string>
     */
    private const STARTER_FILES = [
        'plugins/%plugin%/src/Controllers/ExampleController.php',
        'plugins/%plugin%/src/Blocks/example/block.json',
        'plugins/%plugin%/tests/ExampleTest.php',
        'plugins/%plugin%/REMOVE-EXAMPLE.md',
        'themes/%theme%/package.json',
        'themes/%theme%/assets/src/main.scss',
        'themes/%theme%/inc/Assets.php',
    ];

    public function validate(string $siteDir, string $mode = 'minimal'): ReadinessFinding
    {
        $siteDir = rtrim($siteDir, '/\\');
        $plugin = $this->firstDirectory($siteDir . '/plugins');
        $theme = $this->firstDirectory($siteDir . '/themes');
        $issues = [];
        $evidence = [];

        if ($plugin === null) {
            $issues[] = 'missing:plugins/<client>-site';
        } else {
            $pluginFile = sprintf('plugins/%s/%s.php', $plugin, $plugin);
            $check = $this->requiredFileChecks($siteDir, [$pluginFile]);
            $issues = [...$issues, ...$check['issues']];
            $evidence = [...$evidence, ...$check['evidence']];

            $namespace = $this->namespaceFromProvider($siteDir, $plugin);
            if ($namespace !== null) {
                $evidence[] = 'namespace:' . $namespace;
            }
        }

        if ($theme === null) {
            $issues[] = 'missing:themes/<client>';
        } else {
            $themeJson = sprintf('themes/%s/theme.json', $theme);
            $check = $this->requiredFileChecks($siteDir, [$themeJson]);
            $issues = [...$issues, ...$check['issues']];
            $evidence = [...$evidence, ...$check['evidence']];
            $evidence[] = sprintf('css-prefix:--%s-', $theme);
            $evidence[] = sprintf('option-prefix:%s_', str_replace('-', '_', $theme));
            $evidence[] = 'token-strategy:' . $themeJson;
        }

        $check = $this->requiredFileChecks($siteDir, self::GOVERNANCE_FILES);
        $issues = [...$issues, ...$check['issues']];
        $evidence = [...$evidence, ...$check['evidence']];

        if ($mode === 'starter' && $plugin !== null && $theme !== null) {
            $starterFiles = array_map(
                static fn (string $file): string => str_replace(['%plugin%', '%theme%'], [$plugin, $theme], $file),
                self::STARTER_FILES,
            );
            $check = $this->requiredFileChecks($siteDir, $starterFiles, 'starter:');
            $issues = [...$issues, ...$check['issues']];
            $evidence = [...$evidence, ...$check['evidence']];
        }

        $issues = [...$issues, ...$this->placeholderIssues($siteDir)];

        if ($issues !== []) {
            return new ReadinessFinding(
                'make-site',
                ReadinessFinding::STATUS_FAIL,
                'Generated client-site scaffold is missing required readiness evidence.',
                $issues,
                'client-site',
                true,
                'Regenerate the client scaffold or restore missing governance, token, and starter files.',
            );
        }

        return new ReadinessFinding(
            'make-site',
            ReadinessFinding::STATUS_PASS,
            'Generated client-site scaffold includes isolated plugin/theme, governance, specs, and token strategy.',
            $evidence,
            'client-site',
            false,
            'None',
        );
    }

    /**
     * @param list<string> $relativePaths
     *
     * @return array{issues:list<string>,evidence:list<string>}
     */
    private function requiredFileChecks(
        string $siteDir,
        array $relativePaths,
        string $evidencePrefix = '',
    ): array {
        $issues = [];
        $evidence = [];

        foreach ($relativePaths as $relativePath) {
            if (is_file($siteDir . '/' . $relativePath)) {
                $evidence[] = $evidencePrefix . $relativePath;

                continue;
            }

            $issues[] = 'missing:' . $relativePath;
        }

        return [
            'issues' => $issues,
            'evidence' => $evidence,
        ];
    }

    private function firstDirectory(string $directory): ?string
    {
        if (! is_dir($directory)) {
            return null;
        }

        $entries = array_values(array_filter(
            scandir($directory) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($directory . '/' . $entry),
        ));

        sort($entries);

        return $entries[0] ?? null;
    }

    private function namespaceFromProvider(string $siteDir, string $plugin): ?string
    {
        $provider = glob($siteDir . '/plugins/' . $plugin . '/src/*ServiceProvider.php') ?: [];

        if ($provider === []) {
            return null;
        }

        $name = basename($provider[0], 'ServiceProvider.php');

        return $name . '\\';
    }

    /**
     * @return list<string>
     */
    private function placeholderIssues(string $siteDir): array
    {
        $issues = [];

        if (! is_dir($siteDir)) {
            return ['missing:' . $siteDir];
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($siteDir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, '{{') || str_contains($contents, '}}')) {
                $issues[] = 'unresolved-placeholder:' . str_replace('\\', '/', substr($file->getPathname(), strlen($siteDir) + 1));
            }
        }

        return $issues;
    }
}
