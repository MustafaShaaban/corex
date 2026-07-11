<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Activity;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

/**
 * One indexed, append-only store for product activity across CoreX modules.
 */
final class ActivityTable
{
    public const NAME = 'activity_events';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()
            ->string('event_uuid', 36)
            ->datetime('occurred_at')
            ->integer('actor_id')
            ->string('actor_kind', 16)
            ->string('actor_label', 191)
            ->string('area', 32)
            ->string('kind', 100)
            ->string('target_type', 64)
            ->string('target_id', 191)
            ->string('target_label', 191)
            ->string('outcome', 16)
            ->text('summary')
            ->text('context_json')
            ->string('sensitivity', 16)
            ->datetime('retention_until')
            ->index('event_uuid', ['event_uuid'], unique: true)
            ->index('activity_occurred', ['occurred_at'])
            ->index('activity_area', ['area'])
            ->index('activity_kind', ['kind'])
            ->index('activity_outcome', ['outcome'])
            ->index('activity_actor', ['actor_id'])
            ->index('activity_retention', ['retention_until']);
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(self::NAME, 'Activity events', [
            ['id' => 'occurred_at', 'label' => 'Occurred'],
            ['id' => 'area', 'label' => 'Area'],
            ['id' => 'kind', 'label' => 'Event'],
            ['id' => 'actor_label', 'label' => 'Actor'],
            ['id' => 'target_label', 'label' => 'Target'],
            ['id' => 'outcome', 'label' => 'Outcome'],
        ], 'corex');
    }
}
