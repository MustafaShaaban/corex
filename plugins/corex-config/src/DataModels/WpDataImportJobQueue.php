<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Jobs\JobService;
use DateTimeImmutable;

final readonly class WpDataImportJobQueue implements DataImportJobQueue
{
    public function __construct(private JobService $jobs)
    {
    }

    public function enqueue(DataImportRun $run): int
    {
        return $this->jobs->enqueue(
            DataImportJobHandler::KIND,
            $run->actorId,
            count($run->acceptedRows),
            $run->inputHash,
            new DateTimeImmutable('now'),
        )->id;
    }
}
