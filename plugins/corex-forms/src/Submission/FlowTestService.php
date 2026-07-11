<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowVersion;

/**
 * Runs the complete pipeline with a persisted marked-test submission.
 */
final readonly class FlowTestService
{
    public function __construct(
        private FlowService $flows,
        private FormSubmissionPipeline $pipeline,
    ) {
    }

    /** @param array<string,mixed> $values */
    public function run(int $flowId, int $expectedVersion, array $values): SubmissionPipelineResult
    {
        $flow = $this->flows->get($flowId);
        $version = $this->flows->preview($flowId);
        if ($version->versionNumber !== $expectedVersion) {
            throw new \Corex\Forms\Flow\FlowConflictException(__('The flow draft version is stale.', 'corex'));
        }

        return $this->pipeline->run(new SubmissionPipelineContext(
            $flow,
            $version,
            $values === [] ? $this->sampleValues($version) : $values,
            true,
        ));
    }

    /** @return array<string,mixed> */
    private function sampleValues(FlowVersion $version): array
    {
        $values = [];
        foreach ($version->configuration->schema as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $values[$key] = match ((string) ($field['type'] ?? 'text')) {
                'email' => 'test@example.com',
                'number', 'rating' => 1,
                'checkbox', 'consent' => '1',
                'date' => '2026-07-04',
                'time' => '12:00',
                'url' => 'https://example.com',
                default => 'Test value',
            };
        }

        return $values;
    }
}
