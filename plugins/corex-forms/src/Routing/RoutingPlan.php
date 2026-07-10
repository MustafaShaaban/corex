<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Ordered rules plus the mandatory no-match fallback.
 */
final readonly class RoutingPlan
{
    /** @param list<RoutingRule> $rules */
    public function __construct(
        public array $rules,
        public ?RoutingTarget $fallback,
    ) {
        if ($fallback === null) {
            throw new InvalidArgumentException('A routing fallback is required.');
        }
        foreach ($rules as $rule) {
            if (! $rule instanceof RoutingRule) {
                throw new InvalidArgumentException('A routing plan accepts RoutingRule values only.');
            }
        }
    }
}
