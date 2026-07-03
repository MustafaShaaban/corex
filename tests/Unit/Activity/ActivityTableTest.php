<?php

/**
 * Unit tests for the managed activity table definition (spec 068: FR-018, FR-149).
 *
 * @package Corex\Tests\Unit\Activity
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Activity\ActivityTable;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('defines the append-only activity schema with bounded-query indexes', function () {
    $sql = (new ActivityTable())->schema()->createSql('wp_corex_activity_events', 'DEFAULT CHARSET=utf8mb4');

    expect($sql)->toContain('event_uuid VARCHAR(36) NOT NULL')
        ->toContain('occurred_at DATETIME NOT NULL')
        ->toContain('summary LONGTEXT NOT NULL')
        ->toContain('context_json LONGTEXT NOT NULL')
        ->toContain('retention_until DATETIME NOT NULL')
        ->toContain('UNIQUE KEY event_uuid (event_uuid)')
        ->toContain('KEY activity_area (area)')
        ->toContain('KEY activity_retention (retention_until)');
});

it('publishes the activity table to the managed data registry', function () {
    $managed = (new ActivityTable())->managed();

    expect($managed->name)->toBe(ActivityTable::NAME)
        ->and($managed->columnIds())->toContain('occurred_at', 'area', 'kind', 'outcome');
});
