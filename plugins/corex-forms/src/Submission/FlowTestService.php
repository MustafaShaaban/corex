<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowVersion;
use Corex\Mail\Capture\MailHold;

/**
 * Runs the complete pipeline with a persisted marked-test submission.
 *
 * Every email the run sends is held, on any site: kept in Email Studio and never sent
 * (spec 113, US3; the owner's decision SAFE-D3). The result lists what was held.
 */
final readonly class FlowTestService
{
    public function __construct(
        private FlowService $flows,
        private FormSubmissionPipeline $pipeline,
        private MailHold $hold,
    ) {
    }

    /** @param array<string,mixed> $values */
    public function run(int $flowId, int $expectedVersion, array $values): SubmissionPipelineResult
    {
        $flow = $this->flows->get($flowId);
        $version = $this->flows->preview($flowId);
        if ($version->versionNumber !== $expectedVersion) {
            throw new \Corex\Forms\Flow\FlowConflictException(FlowService::conflict());
        }

        [$result, $held] = $this->hold->during(fn (): SubmissionPipelineResult => $this->pipeline->run(new SubmissionPipelineContext(
            $flow,
            $version,
            $values === [] ? $this->sampleValues($version) : $values,
            true,
        )));

        return $result->withHeldEmails($held);
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
