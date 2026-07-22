<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Jobs;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobDispatcher;
use Corex\Jobs\JobFinishedEvent;
use Corex\Jobs\JobHandlerRegistry;
use Corex\Jobs\JobRepository;
use DateTimeImmutable;
use Throwable;

/**
 * Executes exactly one bounded handler step per scheduled invocation.
 */
final class JobRunner
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobHandlerRegistry $handlers,
        private readonly JobDispatcher $dispatcher,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    public function register(): void
    {
        add_action(ActionSchedulerJobDispatcher::HOOK, [$this, 'run'], 10, 1);
    }

    public function run(int $jobId): void
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || $job->terminal()) {
            return;
        }

        $now = new DateTimeImmutable('now');
        if ($job->state !== BoundedJob::STATE_RUNNING) {
            $job = $job->start($now);
            $this->jobs->save($job);
        }

        $handler = $this->handlers->find($job->kind);
        if ($handler === null) {
            $this->persist($job->fail(__('No handler is registered for this job kind.', 'corex'), $now));

            return;
        }

        try {
            $job = $handler->handle($job, self::BATCH_SIZE);
            $this->persist($job);

            if (! $job->terminal()) {
                $this->dispatcher->dispatch($job);
            }
        } catch (Throwable $exception) {
            $this->persist($job->fail($exception->getMessage(), new DateTimeImmutable('now')));
        }
    }

    /** Save the job and, if it has reached a terminal state, announce it for downstream reactions. */
    private function persist(BoundedJob $job): void
    {
        $this->jobs->save($job);

        if ($job->terminal()) {
            $this->events?->dispatch(new JobFinishedEvent(
                jobId: $job->id,
                kind: $job->kind,
                actorId: $job->actorId,
                state: $job->state,
                errorSummary: $job->errorSummary,
            ));
        }
    }
}
