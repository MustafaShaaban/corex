<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * Decides whether a notification may be delivered over the email channel, and is the guard against
 * the failure→email→failure loop (spec 072 FR-021): a notification in the `email` category, or one
 * whose source is a mail failure, is never emailed. It also enforces a per-window send cap so a
 * storm of notifications cannot become a storm of emails.
 */
interface NotificationChannelPolicy
{
    /** In-app is always allowed; this governs the optional email channel only. */
    public function mayEmail(Notification $notification): bool;
}
