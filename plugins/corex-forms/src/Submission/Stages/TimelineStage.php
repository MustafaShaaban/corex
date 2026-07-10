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
use DateTimeImmutable;

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
        $this->submissions->appendTimeline($context->submissionId, [
            'kind' => 'flow.submitted',
            'state' => 'success',
            'is_test' => $context->isTest,
            'occurred_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'flow_version_id' => $context->version->id,
        ]);

        return SubmissionStageResult::success(
            $this->key(),
            $context->withMetadata(['timeline' => ['recorded' => true]]),
            __('Submission timeline recorded.', 'corex'),
        );
    }
}
