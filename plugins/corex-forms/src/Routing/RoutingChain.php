<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

/**
 * The targets a submission tries, in order, until one names somebody (spec 122, FR-009): the
 * matched rule's target, then the flow's fallback, then the flow's owner.
 *
 * One rule, in one place: the routing stage assigns by it and the Emails tab's preview says who
 * it would reach by it, so the two cannot disagree.
 */
final class RoutingChain
{
    /**
     * @param bool $matchedIsFallback Whether no rule matched, so the matched target is the fallback.
     *
     * @return list<RoutingTarget>
     */
    public static function for(RoutingTarget $matched, RoutingTarget $fallback, bool $matchedIsFallback): array
    {
        $chain = [$matched];
        if (! $matchedIsFallback) {
            $chain[] = $fallback;
        }
        if ($fallback->type !== 'flow_owner') {
            $chain[] = new RoutingTarget('flow_owner');
        }

        return $chain;
    }
}
