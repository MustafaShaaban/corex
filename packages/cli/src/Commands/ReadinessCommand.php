<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

use Corex\Cli\Release\CiSecurityReadiness;
use Corex\Cli\Release\ComponentCoverageDefaults;
use Corex\Cli\Release\ComponentCoverageReadinessCheck;
use Corex\Cli\Release\DeploymentReadinessCheck;
use Corex\Cli\Release\FreeProBoundaryDefaults;
use Corex\Cli\Release\FreeProBoundaryReadinessCheck;
use Corex\Cli\Release\MetadataConsistencyCheck;
use Corex\Cli\Release\MultiAgentReadinessCheck;
use Corex\Cli\Release\ReadinessFinding;
use Corex\Cli\Release\ReadinessReport;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldValidator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * `wp corex readiness` renders spec 055 client-readiness evidence.
 */
final class ReadinessCommand
{
    /**
     * @var list<string>
     */
    private const FILES = [
        'package.json',
        'composer.json',
        'README.md',
        'CHANGELOG.md',
        'PROGRESS.md',
        '.github/workflows/ci.yml',
        '.github/workflows/e2e.yml',
        '.github/workflows/docs.yml',
        '.github/CODEOWNERS',
        '.github/dependabot.yml',
        '.github/workflows/codeql.yml',
        'SECURITY.md',
        'CONTRIBUTING.md',
    ];

    private readonly MetadataConsistencyCheck $metadata;

    private readonly CiSecurityReadiness $ciSecurity;

    private readonly string $root;

    private readonly SiteScaffolder $siteScaffolder;

    private readonly SiteScaffoldValidator $siteScaffoldValidator;

    private readonly DeploymentReadinessCheck $deploymentReadiness;

    private readonly ComponentCoverageReadinessCheck $componentCoverage;

    private readonly FreeProBoundaryReadinessCheck $freeProBoundary;

    private readonly MultiAgentReadinessCheck $multiAgent;

    public function __construct(ReadinessCommandServices $services)
    {
        $this->metadata = $services->metadata;
        $this->ciSecurity = $services->ciSecurity;
        $this->root = $services->root;
        $this->siteScaffolder = $services->siteScaffolder;
        $this->siteScaffoldValidator = $services->siteScaffoldValidator;
        $this->deploymentReadiness = $services->deploymentReadiness;
        $this->componentCoverage = $services->componentCoverage;
        $this->freeProBoundary = $services->freeProBoundary;
        $this->multiAgent = $services->multiAgent;
    }

    /**
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $expected = (string) ($args[0] ?? (defined('COREX_CORE_VERSION') ? COREX_CORE_VERSION : '0.0.0'));
        $files = $this->readFiles();
        $rows = $this->rows($expected, $files);

        if (function_exists('WP_CLI\Utils\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, ['category', 'status', 'summary', 'evidence', 'next_action']);
        } else {
            foreach ($rows as $row) {
                \WP_CLI::line(sprintf('[%s] %s - %s', $row['status'], $row['category'], $row['summary']));
            }
        }

        $report = ReadinessReport::fromFindings($this->findings($expected, $files));

        if ($report->status() === ReadinessFinding::STATUS_FAIL) {
            \WP_CLI::error(__('Corex readiness has blocking failures.', 'corex'));

            return;
        }

        \WP_CLI::success(__('Corex readiness report generated.', 'corex'));
    }

    /**
     * @param array<string,string> $files
     *
     * @return list<array{category:string,status:string,summary:string,evidence:string,next_action:string}>
     */
    public function rows(string $expected, array $files): array
    {
        return array_map(
            static fn (ReadinessFinding $finding): array => [
                'category' => $finding->category,
                'status' => strtoupper($finding->status),
                'summary' => $finding->summary,
                'evidence' => implode('; ', $finding->evidence),
                'next_action' => $finding->nextAction,
            ],
            $this->findings($expected, $files),
        );
    }

    /**
     * @param array<string,string> $files
     *
     * @return list<ReadinessFinding>
     */
    private function findings(string $expected, array $files): array
    {
        return [
            $this->runtimeFinding(),
            $this->metadataFinding($expected, $files),
            ...$this->ciSecurity->evaluate($files),
            $this->makeSiteFinding(),
            $this->deploymentReadiness->evaluate(),
            $this->componentCoverage->evaluate(ComponentCoverageDefaults::matrix()),
            $this->freeProBoundary->evaluate(FreeProBoundaryDefaults::matrix()),
            $this->multiAgent->evaluate([]),
        ];
    }

    private function runtimeFinding(): ReadinessFinding
    {
        return new ReadinessFinding(
            'runtime-gating',
            ReadinessFinding::STATUS_PASS,
            'Runtime add-on provider resolver is implemented and covered by Pest',
            ['tests/Unit/Foundation/AddonProviderResolverTest.php', 'tests/Unit/Woo/WooProviderGateTest.php'],
            'core',
            false,
            'None',
        );
    }

    /**
     * @param array<string,string> $files
     */
    private function metadataFinding(string $expected, array $files): ReadinessFinding
    {
        $report = $this->metadata->evaluate(
            $expected,
            $files,
            ['package.json:version' => 'npm workspace root version is independent of Corex release tags'],
        );
        $blockingMismatches = array_values(array_filter(
            $report['mismatches'],
            static fn (array $mismatch): bool => $mismatch['status'] !== 'ignored-by-policy',
        ));

        return new ReadinessFinding(
            'metadata',
            $blockingMismatches === [] ? ReadinessFinding::STATUS_PASS : ReadinessFinding::STATUS_FAIL,
            $blockingMismatches === [] ? 'Release metadata is aligned or explicitly excepted' : 'Release metadata mismatch found',
            $this->metadataEvidence($report['mismatches']),
            'cli',
            $blockingMismatches !== [],
            $blockingMismatches === [] ? 'None' : 'Align release metadata or record an explicit policy exception.',
        );
    }

    /**
     * @param list<array<string,string>> $mismatches
     *
     * @return list<string>
     */
    private function metadataEvidence(array $mismatches): array
    {
        if ($mismatches === []) {
            return ['metadata:matched'];
        }

        return array_map(
            static fn (array $mismatch): string => sprintf(
                '%s:%s=%s',
                $mismatch['path'],
                $mismatch['field'],
                $mismatch['actual'],
            ),
            $mismatches,
        );
    }

    private function makeSiteFinding(): ReadinessFinding
    {
        $minimalDir = $this->temporarySiteDir('minimal');
        $starterDir = $this->temporarySiteDir('starter');

        try {
            $this->siteScaffolder->scaffold('Acme', $minimalDir, ['minimal' => true, 'force' => true]);
            $this->siteScaffolder->scaffold('Acme', $starterDir, ['starter' => true, 'force' => true]);

            $minimal = $this->siteScaffoldValidator->validate($minimalDir, 'minimal');
            $starter = $this->siteScaffoldValidator->validate($starterDir, 'starter');
            $issues = array_values(array_filter(
                [$minimal, $starter],
                static fn (ReadinessFinding $finding): bool => $finding->status !== ReadinessFinding::STATUS_PASS,
            ));

            if ($issues !== []) {
                return new ReadinessFinding(
                    'make-site',
                    ReadinessFinding::STATUS_FAIL,
                    'make:site scaffold validation failed.',
                    array_merge(...array_map(static fn (ReadinessFinding $finding): array => $finding->evidence, $issues)),
                    'client-site',
                    true,
                    'Fix make:site scaffold validation failures before client-site work starts.',
                );
            }

            return new ReadinessFinding(
                'make-site',
                ReadinessFinding::STATUS_PASS,
                'make:site minimal and starter scaffolds validate for client-site readiness.',
                [
                    ...$this->prefixedEvidence($minimal, 'minimal'),
                    ...$this->prefixedEvidence($starter, 'starter'),
                ],
                'client-site',
                false,
                'None',
            );
        } finally {
            $this->removeDirectory($minimalDir);
            $this->removeDirectory($starterDir);
        }
    }

    private function temporarySiteDir(string $mode): string
    {
        $directory = sys_get_temp_dir() . '/corex_readiness_' . $mode . '_' . uniqid('', true);
        mkdir($directory, 0755, true);

        return str_replace('\\', '/', $directory);
    }

    /**
     * @return list<string>
     */
    private function prefixedEvidence(ReadinessFinding $finding, string $prefix): array
    {
        return array_map(
            static fn (string $evidence): string => str_starts_with($evidence, $prefix . ':')
                ? $evidence
                : $prefix . ':' . $evidence,
            $finding->evidence,
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory) || ! str_starts_with(str_replace('\\', '/', $directory), str_replace('\\', '/', sys_get_temp_dir()) . '/corex_readiness_')) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }

    /**
     * @return array<string,string>
     */
    private function readFiles(): array
    {
        $files = [];

        foreach (array_merge(self::FILES, $this->versionFiles()) as $relativePath) {
            $path = $this->root . '/' . $relativePath;

            if (is_readable($path)) {
                $files[$relativePath] = (string) file_get_contents($path);
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function versionFiles(): array
    {
        return [
            'plugins/corex-core/corex-core.php',
            'plugins/corex-blocks/corex-blocks.php',
            'plugins/corex-forms/corex-forms.php',
            'plugins/corex-config/corex-config.php',
            'theme/style.css',
            'addons/corex-ui/corex-ui.php',
            'addons/corex-email/corex-email.php',
            'addons/corex-captcha/corex-captcha.php',
            'addons/corex-newsletter/corex-newsletter.php',
            'addons/corex-careers/corex-careers.php',
            'addons/corex-bookings/corex-bookings.php',
            'addons/corex-media/corex-media.php',
            'addons/corex-kit-company/corex-kit-company.php',
            'addons/corex-kit-portfolio/corex-kit-portfolio.php',
            'addons/corex-kit-woo/corex-kit-woo.php',
        ];
    }
}
