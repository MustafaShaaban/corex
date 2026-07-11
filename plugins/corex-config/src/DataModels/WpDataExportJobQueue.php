<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Jobs\JobService;
use DateTimeImmutable;

final readonly class WpDataExportJobQueue implements DataExportJobQueue
{
    public function __construct(private JobService $jobs) {}

    public function enqueue(DataExportRun $run): int
    {
        return $this->jobs->enqueue(
            DataExportJobHandler::KIND, $run->actorId, $run->recordCount,
            $run->inputHash, new DateTimeImmutable('now'),
        )->id;
    }
}
