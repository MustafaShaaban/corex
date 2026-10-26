<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowActionRegistry;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Throwable;

/**
 * Runs the flow actions the owner chose, once per stored submission (spec 122, FR-022).
 *
 * It runs last, after storage, routing, emails and the timeline, so an action sees what was kept
 * and cannot stop any of it. Each action's result is a timeline event: done, or failed with its
 * message. The stage itself always succeeds: a failing action is the action's problem, not the
 * visitor's. A marked test runs the actions too, told so with `is_test` and `test_notice`.
 */
final readonly class ActionsStage implements SubmissionStage
{
    public function __construct(
        private FlowActionRegistry $actions,
        private SubmissionRepository $submissions,
    ) {
    }

    public function key(): string
    {
        return 'actions';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $chosen = $context->version->configuration->actions;
        if ($context->submissionId === null || $chosen === []) {
            return SubmissionStageResult::success($this->key(), $context, __('No flow actions to run.', 'corex'));
        }

        $failed = 0;
        foreach ($chosen as $key) {
            [$outcome, $message] = $this->run($key, $context);
            $failed += $outcome === 'failure' ? 1 : 0;
            $this->submissions->appendTimeline($context->submissionId, 'action', $outcome, [
                'action' => $key,
                'label' => $this->actions->has($key) ? $this->actions->label($key) : $key,
                'message' => $message,
            ]);
        }

        return SubmissionStageResult::success(
            $this->key(),
            $context,
            $failed === 0
                ? __('Flow actions ran.', 'corex')
                : __('Flow actions ran; at least one failed. The timeline says which.', 'corex'),
        );
    }

    /** @return array{0:string,1:string} The outcome and the sentence the timeline shows. */
    private function run(string $key, SubmissionPipelineContext $context): array
    {
        if (! $this->actions->has($key)) {
            /* translators: %s: the key of a flow action that is no longer registered. */
            return ['failure', sprintf(__('The action "%s" is not available.', 'corex'), $key)];
        }
        $label = $this->actions->label($key);
        try {
            $this->actions->execute($key, [
                'submission_id' => $context->submissionId,
                'values' => $context->values,
                'flow' => [
                    'id' => $context->flow->id,
                    'slug' => $context->flow->slug,
                    'name' => $context->flow->name,
                    'owner_id' => $context->flow->ownerId,
                ],
                'assignee' => $context->metadata['routing']['assignee'] ?? [],
                'is_test' => $context->isTest,
                'test_notice' => $context->isTest ? __('This is a test.', 'corex') : '',
            ]);
        } catch (Throwable $error) {
            /* translators: 1: the action's name, 2: the error it raised. */
            return ['failure', sprintf(__('%1$s failed: %2$s', 'corex'), $label, $error->getMessage())];
        }

        /* translators: %s: the action's name. */
        return ['success', sprintf(__('%s: done.', 'corex'), $label)];
    }
}
