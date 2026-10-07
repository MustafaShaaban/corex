<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Jobs\JobRunner;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobService;
use DateTimeImmutable;

final readonly class WpSubmissionExportJobQueue implements SubmissionExportJobQueue
{
    public function __construct(private JobService $jobs, private JobRunner $runner)
    {
    }

    public function advance(int $jobId): array
    {
        $this->runner->run($jobId);
        $job = $this->jobs->find($jobId);

        return [
            'state' => $job?->state ?? BoundedJob::STATE_FAILED,
            'processed' => $job?->processed ?? 0,
            'total' => $job?->total ?? 0,
            'error' => (string) ($job?->errorSummary ?? ''),
        ];
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
