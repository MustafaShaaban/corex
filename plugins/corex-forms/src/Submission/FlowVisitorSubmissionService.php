<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowClosedException;
use Corex\Forms\Flow\FlowNotice;
use Corex\Forms\Flow\FlowNoticeText;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowStatusResolver;
use DateTimeImmutable;
use DomainException;

/**
 * Runs visitor input against the flow version the visitor answered (spec 122, DECISIONS #470).
 *
 * A flow that is not taking answers refuses with its notice (FR-029). A capped flow counts and
 * stores under one lock, so the count never goes past its cap (FR-030).
 */
final readonly class FlowVisitorSubmissionService
{
    /** The version the visitor's page was printed with, sent back with the answers. */
    public const VERSION_FIELD = 'corex_flow_version';

    public function __construct(
        private FlowService $flows,
        private FormSubmissionPipeline $pipeline,
        private FlowSchemaFactory $schemas,
        private EventDispatcher $events,
        private FlowStatusResolver $statuses,
        private FlowNoticeText $notices,
        private PageReference $pages,
        private SubmissionRepository $submissions,
    ) {
    }

    /** @param array<string,mixed> $values */
    public function submit(int $flowId, array $values): FlowVisitorResult
    {
        $flow = $this->flows->get($flowId);
        $now = new DateTimeImmutable('now');
        $this->assertOpen($flow, $now);

        $seen = isset($values[self::VERSION_FIELD]) ? (int) $values[self::VERSION_FIELD] : null;
        $pageId = $this->pages->verify($values[PageReference::FIELD] ?? null, $flow->id);
        unset($values[self::VERSION_FIELD], $values[PageReference::FIELD]);
        $version = $this->flows->versionForVisitor($flow, $seen, $now);
        $run = fn (): SubmissionPipelineResult => $this->pipeline->run(new SubmissionPipelineContext(
            $flow,
            $version,
            $values,
            false,
            null,
            ['source' => ['page_id' => $pageId]],
        ));

        $result = $flow->availability->maxResponses === null
            ? $run()
            : $this->submissions->oneAtATime($flow->id, function () use ($flow, $now, $run): SubmissionPipelineResult {
                // Counted again under the lock: another request may have taken the last place.
                $this->assertOpen($flow, $now);

                return $run();
            });
        $this->announce($result);

        return new FlowVisitorResult($result, $version->configuration->success);
    }

    /**
     * The fields a submission to this flow may carry: the live version's, and the questions of the
     * version the visitor's page names, which may have one the live version dropped.
     *
     * @return array<string,\Corex\Forms\Schema\FieldSchema>
     */
    public function schema(int $flowId, ?int $seen = null): array
    {
        $flow = $this->flows->get($flowId);
        $live = $this->flows->liveVersion($flow);
        $schema = $this->schemas->make($live->configuration);
        if ($seen !== null && $flow->state === Flow::STATE_PUBLISHED) {
            $schema += $this->schemas->make($this->flows->versionForVisitor($flow, $seen, new DateTimeImmutable('now'))->configuration);
        }

        return $schema;
    }

    /** @throws FlowClosedException|DomainException */
    private function assertOpen(Flow $flow, DateTimeImmutable $now): void
    {
        $status = $this->statuses->at($flow, $now);
        if ($status->isOpen()) {
            return;
        }
        $notice = $this->notices->notice($flow, $status);
        if (! $notice instanceof FlowNotice) {
            throw new DomainException(__('This flow is not available for submissions.', 'corex'));
        }

        throw new FlowClosedException($notice);
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
}
