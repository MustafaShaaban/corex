<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Access;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched once a user has filed a pending access request, so the Notification Center can alert the
 * access managers without watching the access tables. Immutable: carries the request identity, who
 * asked, and what they asked for (exactly one of an ability or an area, mirroring the request rules).
 */
final class AccessRequestedEvent implements Event
{
    public function __construct(
        public readonly int $requestId,
        public readonly int $requesterId,
        public readonly string $requesterName,
        public readonly ?string $abilityKey,
        public readonly ?string $areaKey,
    ) {
    }
}
