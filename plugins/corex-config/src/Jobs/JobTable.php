<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Jobs;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Table;

final class JobTable
{
    public const NAME = 'bounded_jobs';

    public function schema(): Table
    {
        return (new Table(self::NAME))
            ->id()
            ->string('kind', 100)
            ->integer('actor_id')
            ->string('state', 20)
            ->text('cursor_value')
            ->integer('total')
            ->integer('processed')
            ->integer('succeeded')
            ->integer('failed')
            ->string('input_hash', 64)
            ->string('active_key', 64, nullable: true)
            ->text('result_artifact', nullable: true)
            ->text('error_summary', nullable: true)
            ->integer('attempts')
            ->datetime('next_run_at', nullable: true)
            ->datetime('created_at')
            ->datetime('updated_at')
            ->datetime('finished_at', nullable: true)
            ->index('job_active_key', ['active_key'], unique: true)
            ->index('job_kind', ['kind'])
            ->index('job_actor', ['actor_id'])
            ->index('job_due', ['state', 'next_run_at']);
    }

    public function managed(): ManagedTable
    {
        return new ManagedTable(self::NAME, 'Bounded jobs', [
            ['id' => 'kind', 'label' => 'Kind'],
            ['id' => 'state', 'label' => 'State'],
            ['id' => 'processed', 'label' => 'Processed'],
            ['id' => 'total', 'label' => 'Total'],
            ['id' => 'attempts', 'label' => 'Attempts'],
            ['id' => 'updated_at', 'label' => 'Updated'],
        ], 'corex');
    }
}
