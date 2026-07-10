<?php

/**
 * Ordered routing and fallback contracts (spec 068: FR-034, FR-035).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Routing\RoutingCondition;
use Corex\Forms\Routing\RoutingPlan;
use Corex\Forms\Routing\RoutingRule;
use Corex\Forms\Routing\RoutingService;
use Corex\Forms\Routing\RoutingTarget;

it('evaluates enabled rules by position and returns the first match only', function () {
    $plan = new RoutingPlan(
        rules: [
            new RoutingRule(
                uuid: 'disabled-first',
                position: 1,
                condition: new RoutingCondition('topic', 'equals', 'sales'),
                target: new RoutingTarget('email', ['email' => 'disabled@example.com']),
                enabled: false,
            ),
            new RoutingRule(
                uuid: 'later-specific',
                position: 20,
                condition: new RoutingCondition('topic', 'equals', 'sales'),
                target: new RoutingTarget('role', ['role' => 'sales_manager']),
            ),
            new RoutingRule(
                uuid: 'first-specific',
                position: 10,
                condition: new RoutingCondition('topic', 'equals', 'sales'),
                target: new RoutingTarget('email', ['email' => 'first@example.com']),
            ),
        ],
        fallback: new RoutingTarget('email', ['email' => 'fallback@example.com']),
    );

    $resolved = (new RoutingService())->resolve($plan, ['topic' => 'sales']);

    expect($resolved->matchedRuleUuid)->toBe('first-specific')
        ->and($resolved->target->type)->toBe('email')
        ->and($resolved->target->config['email'])->toBe('first@example.com');
});

it('uses the required fallback when no enabled rule matches', function () {
    $plan = new RoutingPlan(
        rules: [
            new RoutingRule(
                uuid: 'support',
                position: 10,
                condition: new RoutingCondition('topic', 'equals', 'support'),
                target: new RoutingTarget('team', ['team' => 'support']),
            ),
        ],
        fallback: new RoutingTarget('flow_owner'),
    );

    $resolved = (new RoutingService())->resolve($plan, ['topic' => 'sales']);

    expect($resolved->matchedRuleUuid)->toBeNull()
        ->and($resolved->usedFallback)->toBeTrue()
        ->and($resolved->target->type)->toBe('flow_owner');
});

it('rejects a routing plan without a fallback and unsupported targets', function () {
    expect(fn () => new RoutingPlan([], null))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new RoutingTarget('webhook'))->toThrow(InvalidArgumentException::class);
});
