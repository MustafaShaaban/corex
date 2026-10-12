<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a visitor submission has been stored and its notification delivery attempted, so
 * downstream modules (the Notification Center) can react without reaching into the forms pipeline.
 * Immutable: carries the stored submission's identity, its flow, and Phase A's typed
 * {@see NotificationDelivery} outcome — everything a producer needs, nothing it must go query.
 *
 * Dispatched for a form defined in code too. Such a form has no flow: `$flowId` is
 * {@see NO_FLOW}, `$ownerId` is null, and `$flowSlug` is the form's slug, which is what it is
 * known by everywhere.
 */
final class SubmissionProcessedEvent implements Event
{
    /** The flow id of a form defined in code, which has none. */
    public const NO_FLOW = 0;

    public function __construct(
        public readonly int $submissionId,
        public readonly int $flowId,
        public readonly string $flowSlug,
        public readonly ?int $ownerId,
        public readonly NotificationDelivery $delivery,
    ) {
    }
}
