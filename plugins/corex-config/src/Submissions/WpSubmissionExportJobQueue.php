<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Jobs\JobService;
use DateTimeImmutable;

final readonly class WpSubmissionExportJobQueue implements SubmissionExportJobQueue
{
    public function __construct(private JobService $jobs)
    {
    }

    public function enqueue(SubmissionExportRun $run): int
    {
        return $this->jobs->enqueue(
            SubmissionExportJobHandler::KIND,
            $run->actorId,
            $run->recordCount,
            $run->inputHash,
            new DateTimeImmutable('now'),
        )->id;
    }
}
