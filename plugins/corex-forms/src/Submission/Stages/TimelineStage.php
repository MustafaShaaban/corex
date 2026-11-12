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

/**
 * Records that the submission arrived and who it was routed to.
 *
 * It runs before the emails, so an email that cannot be sent never leaves the timeline without the
 * routing; the email stage records its own outcome after it (spec 122, FR-009).
 */
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
}
