<?php

/**
 * Unit tests for managed access persistence definitions (spec 068: FR-084–FR-092).
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Corex\Config\Access\AccessTables;

it('defines indexed role grant and access request tables', function () {
    $tables = (new AccessTables())->schemas();
    $sql    = array_map(
        static fn ($table): string => $table->createSql('wp_corex_' . $table->name, 'DEFAULT CHARSET=utf8mb4'),
        $tables,
    );

    expect($sql[0])->toContain('role_key VARCHAR(64) NOT NULL')
        ->toContain('ability_key VARCHAR(128) NOT NULL')
        ->toContain('UNIQUE KEY role_ability_source (role_key, ability_key, source)')
        ->and($sql[1])->toContain('requester_id BIGINT NOT NULL')
        ->toContain('state VARCHAR(20) NOT NULL')
        ->toContain('KEY access_request_state (state)')
        ->toContain('KEY access_request_expiry (expires_at)');
});

it('publishes both access tables to the managed registry', function () {
    $managed = (new AccessTables())->managed();

    expect(array_map(static fn ($table): string => $table->name, $managed))->toBe([
        AccessTables::ROLE_GRANTS,
        AccessTables::REQUESTS,
    ]);
});
