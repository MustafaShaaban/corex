<?php

/**
 * Unit tests for spec 055 CI/security readiness findings.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\CiSecurityReadiness;
use Corex\Cli\Release\ReadinessFinding;

it('distinguishes repo-file controls from GitHub-settings-only controls', function () {
    $findings = (new CiSecurityReadiness())->evaluate([
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
    ]);

    expect($findings)->toHaveCount(2)
        ->and($findings[0])->toBeInstanceOf(ReadinessFinding::class)
        ->and($findings[0]->summary)->toContain('repo-file')
        ->and($findings[0]->evidence)->toContain('.github/workflows/ci.yml', 'SECURITY.md')
        ->and($findings[1]->summary)->toContain('GitHub settings')
        ->and($findings[1]->status)->toBe('environment-gated')
        ->and($findings[1]->nextAction)->toContain('branch protection');
});

it('reports missing CODEOWNERS, Dependabot, and CodeQL coverage', function () {
    $findings = (new CiSecurityReadiness())->evaluate([
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
    ]);

    expect($findings[0]->status)->toBe('warning')
        ->and($findings[0]->evidence)->toContain('missing:.github/CODEOWNERS')
        ->and($findings[0]->evidence)->toContain('missing:.github/dependabot.yml')
        ->and($findings[0]->evidence)->toContain('missing:.github/workflows/codeql.yml')
        ->and($findings[0]->nextAction)->toContain('CODEOWNERS');
});

it('names only the repo-file controls still missing', function () {
    $findings = (new CiSecurityReadiness())->evaluate([
        '.github/workflows/ci.yml' => 'run: composer test',
        '.github/workflows/e2e.yml' => 'run: npm run test:e2e',
        '.github/workflows/docs.yml' => 'run: npm run build',
        'SECURITY.md' => 'Report security issues privately.',
        'CONTRIBUTING.md' => 'Run the guard gates before opening a PR.',
        '.github/CODEOWNERS' => '* @amElnagdy',
    ]);

    expect($findings[0]->evidence)->not->toContain('missing:.github/CODEOWNERS')
        ->and($findings[0]->nextAction)->not->toContain('CODEOWNERS')
        ->and($findings[0]->nextAction)->toContain('Dependabot', 'CodeQL');
});
