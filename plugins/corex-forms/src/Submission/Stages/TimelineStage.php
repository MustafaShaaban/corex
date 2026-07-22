<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Corex\Mail\MailResult;

final readonly class TimelineStage implements SubmissionStage
{
    public function __construct(private SubmissionRepository $submissions)
    {
    }

    public function key(): string
    {
        return 'timeline';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        if ($context->submissionId === null) {
            return SubmissionStageResult::failure(
                $this->key(),
                $context,
                __('A stored submission is required before timeline recording.', 'corex'),
            );
        }

        $this->submissions->appendTimeline($context->submissionId, 'submitted', 'success', [
            'kind' => 'flow.submitted',
            'is_test' => $context->isTest,
            'flow_version_id' => $context->version->id,
        ]);

        // Record the notification outcome as its own timeline event, so an administrator sees not
        // just "submitted" but whether the notification was accepted, captured, queued, or failed.
        $delivery = $context->metadata['notification_delivery'] ?? null;
        if (is_array($delivery) && isset($delivery['status'])) {
            $status = (string) $delivery['status'];
            $this->submissions->appendTimeline($context->submissionId, 'notification', $this->outcomeFor($status), [
                'delivery_status' => $status,
                'provider' => $delivery['provider'] ?? null,
                'attempt_id' => $delivery['attempt_id'] ?? null,
                'reason' => $delivery['safe_reason'] ?? '',
            ]);
        }

        return SubmissionStageResult::success(
            $this->key(),
            $context->withMetadata(['timeline' => ['recorded' => true]]),
            __('Submission timeline recorded.', 'corex'),
        );
    }

    /** Coarse timeline outcome for a delivery status, so tone-based rendering stays predictable. */
    private function outcomeFor(string $status): string
    {
        return match ($status) {
            MailResult::STATE_ACCEPTED, MailResult::STATE_CAPTURED, MailResult::STATE_SENT, MailResult::STATE_OPENED => 'success',
            MailResult::STATE_QUEUED, MailResult::STATE_SENDING => 'pending',
            MailResult::STATE_FAILED, MailResult::STATE_REJECTED, MailResult::STATE_BOUNCED => 'failure',
            default => 'info', // not_attempted / unavailable
        };
    }
}
