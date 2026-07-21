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
 * The shared notification record — one indexed row per condition, grouped by dedup key. Per-user
 * read/dismiss/snooze state lives in the companion {@see NotificationUserStateTable}. Follows the
 * {@see \Corex\Config\Activity\ActivityTable} pattern exactly.
 */
final class NotificationTable
{
    public const NAME = 'notifications';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()
            ->string('uuid', 36)
            ->string('type', 100)
            ->string('category', 32)
            ->string('severity', 16)
            ->string('source_module', 64)
            ->string('source_type', 64, nullable: true)
            ->string('source_id', 191, nullable: true)
            ->string('title_key', 191)
            ->string('message_key', 191)
            ->text('rendered_json')
            ->string('dedup_key', 191)
            ->integer('occurrences')
            ->datetime('first_occurred_at')
            ->datetime('latest_occurred_at')
            ->datetime('created_at')
            ->datetime('updated_at')
            ->datetime('expires_at', nullable: true)
            ->datetime('resolved_at', nullable: true)
            ->string('resolution_reason', 191, nullable: true)
            ->string('environment', 16, nullable: true)
            ->integer('actor_id', nullable: true)
            ->text('recipient_json')
            ->text('action_json', nullable: true)
            ->text('metadata_json')
            ->index('notification_uuid', ['uuid'], unique: true)
            ->index('notification_dedup', ['dedup_key'], unique: true)
            ->index('notification_category', ['category'])
            ->index('notification_severity', ['severity'])
            ->index('notification_latest', ['latest_occurred_at'])
            ->index('notification_expires', ['expires_at'])
            ->index('notification_resolved', ['resolved_at'])
            ->index('notification_actor', ['actor_id']);
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(self::NAME, 'Notifications', [
            ['id' => 'latest_occurred_at', 'label' => 'Latest'],
            ['id' => 'category', 'label' => 'Category'],
            ['id' => 'severity', 'label' => 'Severity'],
            ['id' => 'type', 'label' => 'Type'],
            ['id' => 'occurrences', 'label' => 'Count'],
            ['id' => 'resolved_at', 'label' => 'Resolved'],
        ], 'corex');
    }
}
