<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Routing\RoutingCondition;
use Corex\Forms\Routing\RoutingPlan;
use Corex\Forms\Routing\RoutingRule;
use Corex\Forms\Routing\RoutingService;
use Corex\Forms\Routing\RoutingTarget;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;

final readonly class RoutingStage implements SubmissionStage
{
    public function __construct(private RoutingService $routing)
    {
    }

    public function key(): string
    {
        return 'routing';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $configuration = $context->version->configuration->routing;
        $resolved = $this->routing->resolve(
            new RoutingPlan(
                $this->rules((array) ($configuration['rules'] ?? [])),
                $this->target((array) ($configuration['fallback'] ?? [])),
            ),
            $context->values,
        );
        $metadata = ['routing' => [
            'matched_rule_uuid' => $resolved->matchedRuleUuid,
            'used_fallback' => $resolved->usedFallback,
            'target_type' => $resolved->target->type,
            'target_config' => $resolved->target->config,
        ]];

        return SubmissionStageResult::success(
            $this->key(),
            $context->withMetadata($metadata),
            __('Submission route resolved.', 'corex'),
        );
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
