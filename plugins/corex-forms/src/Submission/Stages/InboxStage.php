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
        // The routing stage's assignee: a user, a team or a role the inbox's scope can match, or
        // none. Never the route's own type, which named nobody the inbox could show it to (spec 122).
        $assignee = (array) ($context->metadata['routing']['assignee'] ?? []);
        $next = $context->withMetadata(['inbox' => [
            'status' => 'new',
            'owner_type' => (string) ($assignee['type'] ?? 'none'),
            'owner_key' => (string) ($assignee['key'] ?? ''),
        ]]);
        $this->submissions->updatePipelineMetadata($context->submissionId, $next->metadata);

        return SubmissionStageResult::success($this->key(), $next, __('Submission added to the Inbox.', 'corex'));
    }
}
