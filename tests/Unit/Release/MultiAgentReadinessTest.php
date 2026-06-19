<?php

/**
 * Unit tests for spec 055 multi-agent readiness checks.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\AgentWorkUnit;
use Corex\Cli\Release\MultiAgentReadinessCheck;
use Corex\Cli\Release\ReadinessFinding;

it('rejects main work branches, overlapping files, and missing completion guards', function () {
    $finding = (new MultiAgentReadinessCheck())->evaluate([
        AgentWorkUnit::fromArray([
            'branch' => 'main',
            'specPath' => 'specs/055-stable-client-readiness',
            'taskIds' => ['T021'],
            'filesOwned' => ['packages/cli/src/Release/AgentWorkUnit.php'],
            'status' => 'in-progress',
        ]),
        AgentWorkUnit::fromArray([
            'branch' => 'feature/055-stable-client-readiness',
            'specPath' => 'specs/055-stable-client-readiness',
            'taskIds' => ['T022'],
            'filesOwned' => ['packages/cli/src/Release/AgentWorkUnit.php'],
            'handoff' => 'Ready for review.',
            'verification' => ['vendor/bin/pest tests/Unit/Release/MultiAgentReadinessTest.php'],
            'status' => 'done',
        ]),
    ]);

    expect($finding->category)->toBe('multi-agent')
        ->and($finding->status)->toBe(ReadinessFinding::STATUS_FAIL)
        ->and($finding->blocking)->toBeTrue()
        ->and($finding->evidence)->toContain(
            'branch:main:T021',
            'overlap:packages/cli/src/Release/AgentWorkUnit.php:T021,T022',
            'completion:T022:guards missing',
        );
});

it('passes when work units use feature branches, distinct files, and complete evidence', function () {
    $finding = (new MultiAgentReadinessCheck())->evaluate([
        AgentWorkUnit::fromArray([
            'branch' => 'feature/055-agent-a',
            'specPath' => 'specs/055-stable-client-readiness',
            'taskIds' => ['T021'],
            'filesOwned' => ['packages/cli/src/Release/AgentWorkUnit.php'],
            'handoff' => 'Agent A complete.',
            'verification' => ['vendor/bin/pest tests/Unit/Release/AgentWorkUnitTest.php'],
            'guards' => ['test-guard'],
            'status' => 'done',
        ]),
        AgentWorkUnit::fromArray([
            'branch' => 'feature/055-agent-b',
            'specPath' => 'specs/055-stable-client-readiness',
            'taskIds' => ['T022'],
            'filesOwned' => ['packages/cli/src/Release/MultiAgentReadinessCheck.php'],
            'status' => 'in-progress',
        ]),
    ]);

    expect($finding->status)->toBe(ReadinessFinding::STATUS_PASS)
        ->and($finding->blocking)->toBeFalse()
        ->and($finding->evidence)->toContain('multi-agent:clean');
});

