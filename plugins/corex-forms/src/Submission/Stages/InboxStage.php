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

final readonly class InboxStage implements SubmissionStage
{
    public function __construct(private SubmissionRepository $submissions)
    {
    }

    public function key(): string
    {
        return 'inbox';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        if ($context->submissionId === null) {
            return SubmissionStageResult::failure(
                $this->key(),
                $context,
                __('A stored submission is required before Inbox assignment.', 'corex'),
            );
        }
        $routing = (array) ($context->metadata['routing'] ?? []);
        $next = $context->withMetadata(['inbox' => [
            'status' => 'new',
            'owner_type' => (string) ($routing['target_type'] ?? 'none'),
            'owner_key' => $routing['target_config']['value'] ?? null,
        ]]);
        $this->submissions->updatePipelineMetadata($context->submissionId, $next->metadata);

        return SubmissionStageResult::success($this->key(), $next, __('Submission added to the Inbox.', 'corex'));
    }
}
