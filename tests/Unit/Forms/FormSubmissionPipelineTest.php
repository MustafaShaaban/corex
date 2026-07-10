<?php

/**
 * Typed flow-submission pipeline contracts (spec 068: FR-038, FR-042–FR-044).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Submission\FormSubmissionPipeline;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;

function pipelineFlowFixture(): array
{
    $flow = new Flow(
        id: 7,
        uuid: '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
        slug: 'pipeline-flow',
        name: 'Pipeline flow',
        description: '',
        state: Flow::STATE_PUBLISHED,
        ownerId: 11,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 1,
        testMode: true,
        createdBy: 11,
        updatedBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
    );
    $version = new FlowVersion(
        id: 9,
        flowId: 7,
        versionNumber: 1,
        configuration: new FlowConfiguration([], [], [], [], [], []),
        createdBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
    );

    return [$flow, $version];
}

function successfulPipelineStage(string $key): SubmissionStage
{
    return new class($key) implements SubmissionStage {
        public function __construct(private readonly string $stageKey)
        {
        }

        public function key(): string
        {
            return $this->stageKey;
        }

        public function execute(SubmissionPipelineContext $context): SubmissionStageResult
        {
            $trace = [...(array) ($context->metadata['trace'] ?? []), $this->stageKey];

            return SubmissionStageResult::success(
                $this->stageKey,
                $context->withMetadata(['trace' => $trace]),
                $this->stageKey . ' complete',
            );
        }
    };
}

it('executes every stage in order and preserves the marked-test context', function () {
    [$flow, $version] = pipelineFlowFixture();
    $keys = ['validation', 'protection', 'storage', 'routing', 'email', 'inbox', 'timeline'];
    $pipeline = new FormSubmissionPipeline(array_map(successfulPipelineStage(...), $keys));

    $result = $pipeline->run(new SubmissionPipelineContext(
        flow: $flow,
        version: $version,
        values: ['email' => 'sam@example.com'],
        isTest: true,
    ));

    expect($result->completed)->toBeTrue()
        ->and(array_column($result->stages, 'key'))->toBe($keys)
        ->and($result->context->metadata['trace'])->toBe($keys)
        ->and($result->context->isTest)->toBeTrue();
});

it('stops after a failed stage and preserves already committed safe state', function () {
    [$flow, $version] = pipelineFlowFixture();
    $storage = new class implements SubmissionStage {
        public function key(): string
        {
            return 'storage';
        }

        public function execute(SubmissionPipelineContext $context): SubmissionStageResult
        {
            return SubmissionStageResult::success('storage', $context->withSubmissionId(42), 'Stored');
        }
    };
    $email = new class implements SubmissionStage {
        public function key(): string
        {
            return 'email';
        }

        public function execute(SubmissionPipelineContext $context): SubmissionStageResult
        {
            return SubmissionStageResult::failure('email', $context, 'Provider unavailable', retryable: true);
        }
    };
    $never = successfulPipelineStage('timeline');
    $pipeline = new FormSubmissionPipeline([$storage, $email, $never]);

    $result = $pipeline->run(new SubmissionPipelineContext($flow, $version, [], false));

    expect($result->completed)->toBeFalse()
        ->and($result->context->submissionId)->toBe(42)
        ->and(array_column($result->stages, 'key'))->toBe(['storage', 'email'])
        ->and($result->stages[1]->retryable)->toBeTrue();
});
