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

        $this->recordRouting($context->submissionId, (array) ($context->metadata['routing'] ?? []));

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
                'note' => $this->routedNote($context),
            ]);
        }

        return SubmissionStageResult::success(
            $this->key(),
            $context->withMetadata(['timeline' => ['recorded' => true]]),
            __('Submission timeline recorded.', 'corex'),
        );
    }

    /**
     * Each target that named nobody, and who the submission went to instead (spec 122, FR-009), in
     * one event whose `message` the drawer shows as it is.
     *
     * @param array<string,mixed> $routing
     */
    private function recordRouting(int $submissionId, array $routing): void
    {
        $failures = array_values(array_filter((array) ($routing['failures'] ?? []), 'is_array'));
        if ($failures === []) {
            return;
        }
        $label = (string) ($routing['assignee']['label'] ?? '');
        $outcome = $label !== ''
            /* translators: %s: the person, team or role the submission was assigned to instead. */
            ? sprintf(__('Assigned to %s instead.', 'corex'), $label)
            : __('Nobody could be assigned; it is in the inbox for whoever manages submissions.', 'corex');
        $this->submissions->appendTimeline($submissionId, 'routing', 'info', [
            'failures' => $failures,
            'assignee' => $routing['assignee'] ?? [],
            'message' => trim(implode(' ', array_map(static fn (array $failure): string => (string) ($failure['reason'] ?? ''), $failures)) . ' ' . $outcome),
        ]);
    }

    /** The routed owner's note, when an enabled email went to "Routed owner" (DECISIONS #475). */
    private function routedNote(SubmissionPipelineContext $context): string
    {
        foreach ((array) ($context->metadata['email']['bindings'] ?? []) as $binding) {
            if (is_array($binding) && ($binding['note'] ?? '') !== '') {
                return (string) $binding['note'];
            }
        }

        return '';
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
