<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\Assignee;
use Corex\Forms\Routing\AssigneeResolver;
use Corex\Forms\Routing\RoutedRecipients;
use Corex\Forms\Routing\RoutingCondition;
use Corex\Forms\Routing\RoutingPlan;
use Corex\Forms\Routing\RoutingRule;
use Corex\Forms\Routing\RoutingService;
use Corex\Forms\Routing\RoutingSubject;
use Corex\Forms\Routing\RoutingTarget;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;

/**
 * Picks the route and works out who it assigns (spec 122, US-FLW-03).
 *
 * The first matching rule's target, then the flow's fallback, then the flow's owner: the first of
 * them that names a user, a team or a role is the assignee. Each target that named nobody is kept
 * with its reason, for the timeline. The routed owner's addresses are worked out here too, so the
 * email stage and the inbox's "Send again" read the same answer (DECISIONS #475).
 */
final readonly class RoutingStage implements SubmissionStage
{
    public function __construct(
        private RoutingService $routing,
        private AssigneeResolver $assignees,
        private RoutedRecipients $recipients,
    ) {
    }

    public function key(): string
    {
        return 'routing';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $configuration = $context->version->configuration->routing;
        $fallback = $this->target((array) ($configuration['fallback'] ?? []));
        $resolved = $this->routing->resolve(
            new RoutingPlan($this->rules((array) ($configuration['rules'] ?? [])), $fallback),
            $context->values,
        );
        $subject = new RoutingSubject(
            $context->flow,
            $context->values,
            $this->pageId($context),
            $context->isTest,
        );
        $candidates = [$resolved->target];
        if (! $resolved->usedFallback) {
            $candidates[] = $fallback;
        }
        if ($fallback->type !== 'flow_owner') {
            $candidates[] = new RoutingTarget('flow_owner');
        }

        $assignee = Assignee::none();
        $failures = [];
        foreach ($candidates as $candidate) {
            $resolution = $this->assignees->resolve($candidate, $subject);
            if ($resolution->assignee !== null) {
                $assignee = $resolution->assignee;
                break;
            }
            $failures[] = ['target' => $candidate->type, 'reason' => $resolution->reason];
        }

        $metadata = ['routing' => [
            'matched_rule_uuid' => $resolved->matchedRuleUuid,
            'used_fallback' => $resolved->usedFallback,
            'target_type' => $resolved->target->type,
            'target_config' => $resolved->target->config,
            'assignee' => [...$assignee->toArray(), 'label' => $this->assignees->name($assignee)],
            'failures' => $failures,
            'recipients' => $this->recipients->for($assignee, $configuration),
        ]];

        return SubmissionStageResult::success(
            $this->key(),
            $context->withMetadata($metadata),
            $assignee->isNone()
                ? __('Submission route resolved, but it names nobody.', 'corex')
                : __('Submission route resolved.', 'corex'),
        );
    }

    /** The page the form was sent from, as the visitor service read it from the signed field. */
    private function pageId(SubmissionPipelineContext $context): ?int
    {
        $page = $context->metadata['source']['page_id'] ?? null;

        return is_int($page) && $page > 0 ? $page : null;
    }

    /**
     * @param list<mixed> $definitions
     * @return list<RoutingRule>
     */
    private function rules(array $definitions): array
    {
        $rules = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }
            $condition = (array) ($definition['condition'] ?? []);
            $rules[] = new RoutingRule(
                uuid: (string) ($definition['uuid'] ?? ''),
                position: (int) ($definition['position'] ?? 0),
                condition: new RoutingCondition(
                    (string) ($condition['field'] ?? ''),
                    (string) ($condition['operator'] ?? 'equals'),
                    $condition['value'] ?? null,
                ),
                target: $this->target((array) ($definition['target'] ?? [])),
                enabled: (bool) ($definition['enabled'] ?? true),
            );
        }

        return $rules;
    }

    /** @param array<string,mixed> $definition */
    private function target(array $definition): RoutingTarget
    {
        return new RoutingTarget(
            (string) ($definition['type'] ?? ''),
            (array) ($definition['config'] ?? []),
        );
    }
}
