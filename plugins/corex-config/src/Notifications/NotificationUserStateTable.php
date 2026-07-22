<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * One row per (notification, user) recording that user's private state: read, dismissed, snoozed,
 * acknowledged. Kept separate from the shared notification so one user reading or dismissing a
 * shared, condition-based notification never affects anyone else (spec 072 FR-005/FR-010).
 */
final class NotificationUserStateTable
{
    public const NAME = 'notification_user_state';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()
            ->integer('notification_id')
            ->integer('user_id')
            ->datetime('read_at', nullable: true)
            ->datetime('dismissed_at', nullable: true)
            ->datetime('snoozed_until', nullable: true)
            ->datetime('acknowledged_at', nullable: true)
            ->datetime('created_at')
            ->datetime('updated_at')
            ->index('notification_user', ['notification_id', 'user_id'], unique: true)
            ->index('notification_state_user', ['user_id'])
            ->index('notification_state_snoozed', ['snoozed_until']);
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(self::NAME, 'Notification user state', [
            ['id' => 'notification_id', 'label' => 'Notification'],
            ['id' => 'user_id', 'label' => 'User'],
            ['id' => 'read_at', 'label' => 'Read'],
            ['id' => 'dismissed_at', 'label' => 'Dismissed'],
        ], 'corex');
    }
}
