<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FlowEmailSender;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionRepository;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Corex\Mail\MailResult;

/**
 * Delivers every enabled flow binding and records per-event outcomes.
 */
final readonly class EmailStage implements SubmissionStage
{
    public function __construct(
        private FlowEmailSender $sender,
        private SubmissionRepository $submissions,
    ) {
    }

    public function key(): string
    {
        return 'email';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $bindings = array_values(array_filter(
            $context->version->configuration->emailRoutes,
            static fn (mixed $binding): bool => is_array($binding) && ($binding['enabled'] ?? false),
        ));
        $primary = array_values(array_filter(
            $bindings,
            static fn (array $binding): bool => ($binding['event'] ?? '') !== 'admin_failure',
        ));
        if ($primary === []) {
            $next = $context->withMetadata(['email' => ['state' => 'not_configured', 'bindings' => []]]);
            $this->persist($next);

            return SubmissionStageResult::success($this->key(), $next, __('No flow email binding is enabled.', 'corex'));
        }

        $outcomes = [];
        $failed = null;
        $retryable = false;
        foreach ($primary as $binding) {
            $result = $this->sender->send($binding, $context->flow->slug, $this->mailContext($context));
            $event = (string) ($binding['event'] ?? 'submitted');
            $outcomes[$event] = $this->outcome($result, (int) ($binding['template_id'] ?? 0));
            if ($result === null || ! $result->successful()) {
                $failed ??= $result?->message ?? __('The enabled flow email binding could not be resolved.', 'corex');
                $retryable = $retryable || ($result?->retryable ?? false);
            }
        }
        if ($failed !== null) {
            $outcomes += $this->failureAlerts($bindings, $context, $failed);
        }
        $next = $context->withMetadata(['email' => [
            'state' => $failed === null ? 'complete' : 'failed',
            'bindings' => $outcomes,
        ]]);
        $this->persist($next);

        return $failed === null
            ? SubmissionStageResult::success($this->key(), $next, __('Flow email bindings completed.', 'corex'))
            : SubmissionStageResult::failure($this->key(), $next, $failed, $retryable);
    }

    /** @return array<string,mixed> */
    private function mailContext(SubmissionPipelineContext $context): array
    {
        return [
            'submission' => $context->values,
            'flow' => [
                'id' => $context->flow->id,
                'slug' => $context->flow->slug,
                'owner_id' => $context->flow->ownerId,
            ],
            'routing' => $context->metadata['routing'] ?? [],
            'is_test' => $context->isTest,
        ];
    }

    /** @return array<string,mixed> */
    private function outcome(?MailResult $result, int $templateId): array
    {
        return [
            'template_id' => $templateId,
            'state' => $result?->state ?? 'unrouted',
            'attempt_id' => $result?->attemptId,
            'provider' => $result?->provider,
            'retryable' => $result?->retryable ?? false,
        ];
    }

    /**
     * @param list<array<string,mixed>> $bindings
     * @return array<string,array<string,mixed>>
     */
    private function failureAlerts(array $bindings, SubmissionPipelineContext $context, string $message): array
    {
        $outcomes = [];
        foreach ($bindings as $binding) {
            if (($binding['event'] ?? '') !== 'admin_failure') {
                continue;
            }
            $mailContext = $this->mailContext($context);
            $mailContext['failure'] = ['message' => $message];
            $result = $this->sender->send($binding, $context->flow->slug, $mailContext);
            $outcomes['admin_failure'] = $this->outcome($result, (int) ($binding['template_id'] ?? 0));
        }

        return $outcomes;
    }

    private function persist(SubmissionPipelineContext $context): void
    {
        if ($context->submissionId !== null) {
            $this->submissions->updatePipelineMetadata($context->submissionId, $context->metadata);
        }
    }
}
