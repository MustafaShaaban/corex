<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessRequestStore;
use Corex\Access\AccessRequestSurfaceState;
use Corex\Access\PendingAccessRequest;

/**
 * The signed-in user's own access-request state, for the denied surface (spec 079, FR-004/005/006).
 *
 * The user ID is taken from the session here and nowhere else, so no caller can ask about somebody
 * else's requests by passing an ID — the surface renders for the person looking at it.
 */
final class CurrentUserAccessRequests implements AccessRequestSurfaceState
{
    public function __construct(
        private readonly AccessRequestStore $requests,
        private readonly AccessRequestFlash $flash,
    ) {
    }

    public function pendingForCurrentUser(string $abilityKey): ?PendingAccessRequest
    {
        $userId = get_current_user_id();
        if ($userId < 1 || $abilityKey === '') {
            return null;
        }

        $row = $this->requests->pendingFor($userId, $abilityKey, null);
        if ($row === null) {
            return null;
        }

        return new PendingAccessRequest(
            id: (int) $row['id'],
            requestedAt: $row['createdAt'],
            expiresAt: $row['expiresAt'],
        );
    }

    public function problemForCurrentUser(): ?array
    {
        return $this->flash->take(get_current_user_id());
    }
}
