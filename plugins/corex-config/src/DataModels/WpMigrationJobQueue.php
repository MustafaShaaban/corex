<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Jobs\JobService;
use DateTimeImmutable;

final readonly class WpMigrationJobQueue implements MigrationJobQueue
{
    public function __construct(private JobService $jobs) {}
    public function enqueue(MigrationRun $run): int
    {
        return $this->jobs->enqueue(
            MigrationJobHandler::KIND, $run->actorId, 1, $run->inputHash,
            new DateTimeImmutable('now'),
        )->id;
    }
}
