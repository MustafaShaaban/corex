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
 */
final class SubmissionProcessedEvent implements Event
{
    public function __construct(
        public readonly int $submissionId,
        public readonly int $flowId,
        public readonly string $flowSlug,
        public readonly ?int $ownerId,
        public readonly NotificationDelivery $delivery,
    ) {
    }
}
