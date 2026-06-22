<?php

/**
 * Unit tests for the `wp corex readiness` report surface (spec 055 T018).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Commands\ReadinessCommand;
use Corex\Cli\Commands\ReadinessCommandServices;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Release\CiSecurityReadiness;
use Corex\Cli\Release\ComponentCoverageReadinessCheck;
use Corex\Cli\Release\DeploymentReadinessCheck;
use Corex\Cli\Release\FreeProBoundaryReadinessCheck;
use Corex\Cli\Release\MetadataConsistencyCheck;
use Corex\Cli\Release\MultiAgentReadinessCheck;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldValidator;

function spec055ReadinessCommand(): ReadinessCommand
{
    return new ReadinessCommand(ReadinessCommandServices::fromArray([
        'metadata' => new MetadataConsistencyCheck(),
        'ciSecurity' => new CiSecurityReadiness(),
        'root' => dirname(__DIR__, 3),
        'siteScaffolder' => new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs'),
        'siteScaffoldValidator' => new SiteScaffoldValidator(),
        'deploymentReadiness' => new DeploymentReadinessCheck(),
        'componentCoverage' => new ComponentCoverageReadinessCheck(),
        'freeProBoundary' => new FreeProBoundaryReadinessCheck(),
        'multiAgent' => new MultiAgentReadinessCheck(),
    ]));
}

it('builds a readiness report covering every required category', function () {
    $command = spec055ReadinessCommand();

    $rows = $command->rows('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.1\n",
        'README.md' => 'Status: latest release **v0.26.1**.',
        'CHANGELOG.md' => "## [0.26.1] - 2026-06-15\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.1.',
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
    ]);

    $categories = array_column($rows, 'category');

    expect($categories)->toContain(
        'runtime-gating',
        'metadata',
        'ci-security',
        'make-site',
        'deployment',
        'component-coverage',
        'free-pro',
        'multi-agent',
    );
});

it('renders exact evidence and next actions for failing or gated checks', function () {
    $command = spec055ReadinessCommand();

    $rows = $command->rows('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.0\n",
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
    ]);

    expect($rows[1]['category'])->toBe('metadata')
        ->and($rows[1]['status'])->toBe('FAIL')
        ->and($rows[1]['evidence'])->toContain('plugins/corex-core/corex-core.php:Version=0.26.0')
        ->and($rows[2]['category'])->toBe('ci-security')
        ->and($rows[2]['evidence'])->toContain('missing:.github/CODEOWNERS')
        ->and($rows[3]['status'])->toBe('ENVIRONMENT-GATED')
        ->and($rows[3]['next_action'])->toContain('branch protection');
});

it('runs make-site and deployment readiness checks after US3 wiring', function () {
    $command = spec055ReadinessCommand();

    $rows = $command->rows('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.1\n",
        'README.md' => 'Status: latest release **v0.26.1**.',
        'CHANGELOG.md' => "## [0.26.1] - 2026-06-15\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.1.',
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
        '.github/CODEOWNERS' => '* @amElnagdy',
    ]);

    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[$row['category']][] = $row;
    }

    expect($byCategory['make-site'][0]['status'])->toBe('PASS')
        ->and($byCategory['make-site'][0]['evidence'])->toContain('minimal:acme-site/acme-site.php')
        ->and($byCategory['make-site'][0]['evidence'])->toContain('starter:acme-site/src/Controllers/ExampleController.php')
        ->and($byCategory['deployment'][0]['status'])->toBe('ENVIRONMENT-GATED')
        ->and($byCategory['deployment'][0]['evidence'])->toContain('profile:azure-container');
});

it('runs component coverage readiness after US4 wiring', function () {
    $command = spec055ReadinessCommand();

    $rows = $command->rows('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.1\n",
        'README.md' => 'Status: latest release **v0.26.1**.',
        'CHANGELOG.md' => "## [0.26.1] - 2026-06-15\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.1.',
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
        '.github/CODEOWNERS' => '* @amElnagdy',
    ]);

    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[$row['category']][] = $row;
    }

    expect($byCategory['component-coverage'][0]['status'])->toBe('PASS')
        ->and($byCategory['component-coverage'][0]['summary'])->toContain('native-first')
        ->and($byCategory['component-coverage'][0]['evidence'])->toContain('home:pattern')
        ->and($byCategory['component-coverage'][0]['evidence'])->toContain('media:wordpress-core-block-style')
        ->and($byCategory['component-coverage'][0]['evidence'])->toContain('navigation:wordpress-core-block-style');
});

it('runs Free/Core boundary readiness after US5 wiring', function () {
    $command = spec055ReadinessCommand();

    $rows = $command->rows('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.1\n",
        'README.md' => 'Status: latest release **v0.26.1**.',
        'CHANGELOG.md' => "## [0.26.1] - 2026-06-15\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.1.',
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
        '.github/CODEOWNERS' => '* @amElnagdy',
    ]);

    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[$row['category']][] = $row;
    }

    expect($byCategory['free-pro'][0]['status'])->toBe('PASS')
        ->and($byCategory['free-pro'][0]['summary'])->toContain('Free/Core basics are protected')
        ->and($byCategory['free-pro'][0]['evidence'])->toContain('free-core:accessibility')
        ->and($byCategory['free-pro'][0]['evidence'])->toContain('free-core:basic make:site')
        ->and($byCategory['free-pro'][0]['evidence'])->toContain('pro-candidate:bookings');
});

it('runs multi-agent readiness after Phase 8 wiring', function () {
    $command = spec055ReadinessCommand();

    $rows = $command->rows('0.26.1', [
        'plugins/corex-core/corex-core.php' => " * Version: 0.26.1\n",
        'README.md' => 'Status: latest release **v0.26.1**.',
        'CHANGELOG.md' => "## [0.26.1] - 2026-06-15\n",
        'PROGRESS.md' => 'Verified release baseline is v0.26.1.',
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
        '.github/CODEOWNERS' => '* @amElnagdy',
    ]);

    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[$row['category']][] = $row;
    }

    expect($byCategory['multi-agent'][0]['status'])->toBe('PASS')
        ->and($byCategory['multi-agent'][0]['summary'])->toContain('branch-isolated')
        ->and($byCategory['multi-agent'][0]['evidence'])->toContain('multi-agent:clean');
});
