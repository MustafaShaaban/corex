<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * Traceable result of resolving one routing plan.
 */
final readonly class ResolvedRouting
{
    public function __construct(
        public RoutingTarget $target,
        public ?string $matchedRuleUuid,
        public bool $usedFallback,
    ) {
    }
}
