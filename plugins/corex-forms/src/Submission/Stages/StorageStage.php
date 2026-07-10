<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FlowSubmissionRecord;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;

final readonly class StorageStage implements SubmissionStage
{
    public function __construct(private SubmissionRepository $submissions)
    {
    }

    public function key(): string
    {
        return 'storage';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $submissionId = $this->submissions->storeFlow(new FlowSubmissionRecord(
            flowSlug: $context->flow->slug,
            flowId: $context->flow->id,
            flowVersionId: $context->version->id,
            flowLabel: $context->flow->name,
            isTest: $context->isTest,
            values: $context->values,
            metadata: $context->metadata,
        ));

        return SubmissionStageResult::success(
            $this->key(),
            $context->withSubmissionId($submissionId),
            __('Submission stored.', 'corex'),
        );
    }
}
