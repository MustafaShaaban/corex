<?php

/**
 * Unit tests for spec 055 multi-agent work-unit metadata.
 *
 * @package Corex\Tests\Unit\Release
 */

declare(strict_types=1);

use Corex\Cli\Release\AgentWorkUnit;

it('requires identity fields for every work unit', function (array $missing) {
    $attributes = array_diff_key([
        'branch' => 'feature/055-stable-client-readiness',
        'specPath' => 'specs/055-stable-client-readiness',
        'taskIds' => ['T021'],
        'filesOwned' => ['packages/cli/src/Release/AgentWorkUnit.php'],
        'status' => 'in-progress',
    ], array_flip($missing));

    expect(fn () => AgentWorkUnit::fromArray($attributes))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'branch' => [['branch']],
    'spec path' => [['specPath']],
    'task ids' => [['taskIds']],
    'files owned' => [['filesOwned']],
    'status' => [['status']],
]);

it('requires completed work units to carry handoff, verification, and guard evidence', function () {
    $unit = AgentWorkUnit::fromArray([
        'branch' => 'feature/055-stable-client-readiness',
        'specPath' => 'specs/055-stable-client-readiness',
        'taskIds' => ['T021', 'T022'],
        'filesOwned' => [
            'tests/Unit/Release/AgentWorkUnitTest.php',
            'tests/Unit/Release/MultiAgentReadinessTest.php',
        ],
        'status' => 'done',
    ]);

    expect($unit->completionIssues())->toContain(
        'handoff missing',
        'verification missing',
        'guards missing',
    );
});

it('accepts completed work units with full evidence', function () {
    $unit = AgentWorkUnit::fromArray([
        'branch' => 'feature/055-stable-client-readiness',
        'specPath' => 'specs/055-stable-client-readiness',
        'taskIds' => ['T021'],
        'filesOwned' => ['packages/cli/src/Release/AgentWorkUnit.php'],
        'handoff' => 'T021 complete; T022 next.',
        'verification' => ['vendor/bin/pest tests/Unit/Release/AgentWorkUnitTest.php'],
        'guards' => ['test-guard'],
        'status' => 'done',
    ]);

    expect($unit->completionIssues())->toBe([])
        ->and($unit->taskIds)->toBe(['T021'])
        ->and($unit->filesOwned)->toBe(['packages/cli/src/Release/AgentWorkUnit.php']);
});

