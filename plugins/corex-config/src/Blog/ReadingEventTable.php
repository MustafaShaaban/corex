<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * Managed table for privacy-preserving first-party Blog analytics events.
 */
final class ReadingEventTable
{
    public const NAME = 'blog_reading_events';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()
            ->integer('post_id')
            ->string('event_type', 24)
            ->string('visitor_hash', 64)
            ->datetime('occurred_at')
            ->integer('reading_seconds', nullable: true)
            ->string('share_target', 64, nullable: true)
            ->datetime('retention_until')
            ->index('reading_events_post_window', ['post_id', 'occurred_at'])
            ->index('reading_events_type', ['event_type'])
            ->index('reading_events_visitor', ['visitor_hash'])
            ->index('reading_events_retention', ['retention_until']);
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(self::NAME, 'Blog reading events', [
            ['id' => 'occurred_at', 'label' => 'Occurred'],
            ['id' => 'post_id', 'label' => 'Post'],
            ['id' => 'event_type', 'label' => 'Event'],
            ['id' => 'reading_seconds', 'label' => 'Reading seconds'],
            ['id' => 'share_target', 'label' => 'Share target'],
            ['id' => 'retention_until', 'label' => 'Retention until'],
        ], 'corex');
    }
}
