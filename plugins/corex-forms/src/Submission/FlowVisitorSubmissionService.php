<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
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
        private EventDispatcher $events,
    ) {
    }

    /** @param array<string,mixed> $values */
    public function submit(int $flowId, array $values): FlowVisitorResult
    {
        $flow = $this->flows->get($flowId);
        $version = $this->flows->publishedVersion($flow);
        $result = $this->pipeline->run(new SubmissionPipelineContext($flow, $version, $values, false));
        $this->announce($result);

        return new FlowVisitorResult($result, $version->configuration->success);
    }

    /**
     * Publish the processed submission for downstream modules (the Notification Center). Fired only
     * once a submission was actually stored; carries Phase A's typed delivery outcome so a producer
     * reacts without touching the forms pipeline.
     */
    private function announce(SubmissionPipelineResult $result): void
    {
        $context = $result->context;
        if ($context->submissionId === null) {
            return;
        }

        $deliveryData = $context->metadata['notification_delivery'] ?? null;
        $delivery = is_array($deliveryData)
            ? NotificationDelivery::fromArray($deliveryData)
            : NotificationDelivery::unavailable();

        $this->events->dispatch(new SubmissionProcessedEvent(
            submissionId: $context->submissionId,
            flowId: $context->flow->id,
            flowSlug: $context->flow->slug,
            ownerId: $context->flow->ownerId,
            delivery: $delivery,
        ));
    }

    /** @return array<string,\Corex\Forms\Schema\FieldSchema> */
    public function schema(int $flowId): array
    {
        $flow = $this->flows->get($flowId);

        return $this->schemas->make($this->flows->publishedVersion($flow)->configuration);
    }
}
