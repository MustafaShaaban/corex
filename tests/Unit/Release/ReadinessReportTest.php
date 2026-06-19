<?php

/**
 * Unit tests for the spec 055 readiness report skeleton.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\ReadinessFinding;
use Corex\Cli\Release\ReadinessReport;

it('requires every readiness category to be represented', function () {
    $report = ReadinessReport::fromFindings([
        new ReadinessFinding('runtime-gating', 'pass', 'Runtime gate checked', ['pest'], 'core', false, 'None'),
        new ReadinessFinding('metadata', 'pass', 'Metadata checked', ['metadata'], 'cli', false, 'None'),
        new ReadinessFinding('ci-security', 'warning', 'CI checked', ['ci.yml'], 'repo-settings', false, 'Add CodeQL'),
        new ReadinessFinding('make-site', 'pass', 'Scaffold checked', ['make:site'], 'cli', false, 'None'),
        new ReadinessFinding('deployment', 'environment-gated', 'Docker unavailable', ['npm run env:start'], 'docs', false, 'Run under wp-env'),
        new ReadinessFinding('component-coverage', 'pass', 'Matrix checked', ['matrix'], 'docs', false, 'None'),
        new ReadinessFinding('free-pro', 'pass', 'Boundary checked', ['boundary'], 'docs', false, 'None'),
        new ReadinessFinding('multi-agent', 'pass', 'Workflow checked', ['AGENTS.md'], 'docs', false, 'None'),
    ]);

    expect($report->isComplete())->toBeTrue()
        ->and($report->missingCategories())->toBe([])
        ->and($report->status())->toBe('warning');
});

it('names missing categories', function () {
    $report = ReadinessReport::fromFindings([
        new ReadinessFinding('runtime-gating', 'pass', 'Runtime gate checked', ['pest'], 'core', false, 'None'),
    ]);

    expect($report->isComplete())->toBeFalse()
        ->and($report->missingCategories())->toContain('metadata', 'ci-security', 'make-site');
});

it('requires environment-gated findings to carry evidence and a next action', function () {
    expect(fn () => new ReadinessFinding('deployment', 'environment-gated', 'Docker unavailable', [], 'docs', false, 'Run it'))
        ->toThrow(\InvalidArgumentException::class)
        ->and(fn () => new ReadinessFinding('deployment', 'environment-gated', 'Docker unavailable', ['npm run env:start'], 'docs', false, ''))
        ->toThrow(\InvalidArgumentException::class);
});
