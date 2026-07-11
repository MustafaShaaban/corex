<?php

/**
 * Unit tests for bounded-job managed persistence (spec 068 shared foundation).
 *
 * @package Corex\Tests\Unit\Jobs
 */

declare(strict_types=1);

use Corex\Config\Jobs\JobTable;

it('defines resumable job state with an active idempotency constraint', function () {
    $sql = (new JobTable())->schema()->createSql('wp_corex_bounded_jobs', 'DEFAULT CHARSET=utf8mb4');

    expect($sql)->toContain('kind VARCHAR(100) NOT NULL')
        ->toContain('active_key VARCHAR(64) NULL')
        ->toContain('cursor_value LONGTEXT NOT NULL')
        ->toContain('result_artifact LONGTEXT NULL')
        ->toContain('UNIQUE KEY job_active_key (active_key)')
        ->toContain('KEY job_due (state, next_run_at)');
});

it('publishes the job table to the managed data registry', function () {
    $managed = (new JobTable())->managed();

    expect($managed->name)->toBe(JobTable::NAME)
        ->and($managed->columnIds())->toContain('kind', 'state', 'processed', 'total', 'updated_at');
});
