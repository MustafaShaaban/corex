<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a grant made by approving an access request has ended (spec 112, FR-005 and
 * FR-006): revoked by an administrator, or ended by its expiry. The Notification Center tells the
 * person and the approver when an expiry ends it.
 */
final class AccessGrantEndedEvent implements Event
{
    /**
     * @param string       $reason     {@see AccessRequestStore::ENDED_REVOKED} or {@see AccessRequestStore::ENDED_EXPIRED}.
     * @param list<string> $abilityKeys What the grant gave.
     */
    public function __construct(
        public readonly int $requestId,
        public readonly int $personId,
        public readonly ?int $approverId,
        public readonly array $abilityKeys,
        public readonly string $reason,
    ) {
    }
}
