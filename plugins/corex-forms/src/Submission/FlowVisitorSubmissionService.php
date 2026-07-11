<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowService;

/**
 * Runs visitor input against the currently published immutable flow version.
 */
final readonly class FlowVisitorSubmissionService
{
    public function __construct(
        private FlowService $flows,
        private FormSubmissionPipeline $pipeline,
        private FlowSchemaFactory $schemas,
    ) {
    }

    /** @param array<string,mixed> $values */
    public function submit(int $flowId, array $values): FlowVisitorResult
    {
        $flow = $this->flows->get($flowId);
        $version = $this->flows->publishedVersion($flow);
        $result = $this->pipeline->run(new SubmissionPipelineContext($flow, $version, $values, false));

        return new FlowVisitorResult($result, $version->configuration->success);
    }

    /** @return array<string,\Corex\Forms\Schema\FieldSchema> */
    public function schema(int $flowId): array
    {
        $flow = $this->flows->get($flowId);

        return $this->schemas->make($this->flows->publishedVersion($flow)->configuration);
    }
}
