<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched whenever site readiness is evaluated (the checks are local, never remote — FR-015), so
 * the Notification Center can reconcile its readiness notifications against the current evidence
 * without running the checks itself. Immutable: carries the whole snapshot, blockers and passes alike,
 * so a consumer can both raise and clear conditions in one pass.
 */
final class ReadinessEvaluatedEvent implements Event
{
    public function __construct(public readonly ReadinessSnapshot $snapshot)
    {
    }
}
