<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a background job reaches a terminal state, so downstream modules (the Notification
 * Center) can react to a failure or completion without polling the job table. Immutable: carries the
 * job identity, its kind, who ran it, its terminal {@see BoundedJob} state, and — for diagnostics on
 * the job screen, not for surfacing verbatim — the raw error summary.
 */
final class JobFinishedEvent implements Event
{
    public function __construct(
        public readonly int $jobId,
        public readonly string $kind,
        public readonly int $actorId,
        public readonly string $state,
        public readonly ?string $errorSummary = null,
    ) {
    }
}
