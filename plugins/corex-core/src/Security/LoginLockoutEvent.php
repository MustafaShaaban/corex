<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

use Corex\Events\Event;
use DateTimeImmutable;

/**
 * Dispatched the moment login protection locks an identity out after too many failed attempts, so the
 * Notification Center can alert the security managers without watching the attempts table. Immutable:
 * carries the locked identity, the client IP that tripped it, and when the lockout lifts.
 */
final class LoginLockoutEvent implements Event
{
    public function __construct(
        public readonly string $identity,
        public readonly string $clientIp,
        public readonly DateTimeImmutable $lockedUntil,
    ) {
    }
}
