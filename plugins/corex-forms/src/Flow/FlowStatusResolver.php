<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Works out a flow's {@see FlowStatus} from its stored state, its dates and its response count.
 *
 * The count is asked only of a flow with a cap, so a page with an uncapped flow reads nothing more.
 */
final readonly class FlowStatusResolver
{
    public function __construct(private FlowResponseCounter $counter)
    {
    }

    public function at(Flow $flow, DateTimeImmutable $now): FlowStatus
    {
        $availability = $flow->availability;

        return match (true) {
            $flow->state === Flow::STATE_DRAFT => new FlowStatus(FlowStatus::DRAFT),
            $flow->state === Flow::STATE_CLOSED => new FlowStatus(FlowStatus::CLOSED, FlowStatus::BY_HAND, $flow->closedAt),
            $flow->state === Flow::STATE_EXPIRED => new FlowStatus(FlowStatus::EXPIRED, FlowStatus::BY_HAND, $flow->expiresAt),
            $availability->opensAt !== null && $now < $availability->opensAt
                => new FlowStatus(FlowStatus::SCHEDULED, FlowStatus::BY_DATE, $availability->opensAt),
            $availability->closesAt !== null && $now >= $availability->closesAt
                => new FlowStatus(FlowStatus::EXPIRED, FlowStatus::BY_DATE, $availability->closesAt),
            default => $this->underCap($flow),
        };
    }

    private function underCap(Flow $flow): FlowStatus
    {
        $cap = $flow->availability->maxResponses;
        if ($cap === null) {
            return new FlowStatus(FlowStatus::OPEN);
        }
        $responses = $this->counter->responses($flow->id);

        return $responses >= $cap
            ? new FlowStatus(FlowStatus::EXPIRED, FlowStatus::BY_CAP, null, $responses)
            : new FlowStatus(FlowStatus::OPEN, '', null, $responses);
    }
}
