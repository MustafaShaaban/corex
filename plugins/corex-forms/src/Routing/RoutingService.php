<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * Resolves ordered routing rules with first-match-wins semantics.
 */
final class RoutingService
{
    /** @param array<string,mixed> $values */
    public function resolve(RoutingPlan $plan, array $values): ResolvedRouting
    {
        $rules = $plan->rules;
        usort($rules, static fn (RoutingRule $left, RoutingRule $right): int => $left->position <=> $right->position);

        foreach ($rules as $rule) {
            if ($rule->enabled && $rule->condition->matches($values)) {
                return new ResolvedRouting($rule->target, $rule->uuid, false);
            }
        }

        return new ResolvedRouting($plan->fallback, null, true);
    }
}
