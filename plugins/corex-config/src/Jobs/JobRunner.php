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
use Corex\Multisite\SiteScope;
use DateTimeImmutable;
use Throwable;

/**
 * Executes exactly one bounded handler step per scheduled invocation.
 */
final class JobRunner
{
    private const BATCH_SIZE = 100;

    /** One option per job being run. `add_option()` fails when the row exists, which is the lock. */
    private const LOCK_PREFIX = 'corex_job_running_';

    /** A run that died holding its lock is taken to have ended after this many seconds. */
    private const LOCK_SECONDS = 120;

    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobHandlerRegistry $handlers,
        private readonly JobDispatcher $dispatcher,
        private readonly SiteScope $siteScope,
        private readonly ?EventDispatcher $events = null,
    ) {
    }

    public function register(): void
    {
        add_action(ActionSchedulerJobDispatcher::HOOK, [$this, 'run'], 10, 2);
    }

    public function run(int $jobId, ?int $siteId = null): void
    {
        $this->siteScope->run(
            $siteId ?? $this->siteScope->currentSiteId(),
            function () use ($jobId): void {
                $this->execute($jobId);
            },
        );
    }

    /**
     * One step of a job, unless another run of the same job is already taking one.
     *
     * A job is advanced by the scheduler, and also by a screen that is waiting for it. Two runs at
     * once would each read the job at the same point and do the same batch twice.
     */
    private function execute(int $jobId): void
    {
        if (! $this->lock($jobId)) {
            return;
        }

        try {
            $this->step($jobId);
        } finally {
            delete_option(self::LOCK_PREFIX . $jobId);
        }
    }

    private function lock(int $jobId): bool
    {
        $name = self::LOCK_PREFIX . $jobId;

        if (add_option($name, time(), '', false)) {
            return true;
        }

        if (time() - (int) get_option($name, 0) < self::LOCK_SECONDS) {
            return false;
        }

        // Held by a run that never let go. Its time is replaced, so two runs that both find it
        // stale do not both proceed: only one of them changes the value.
        return update_option($name, time(), false);
    }

    private function step(int $jobId): void
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

        // Run as the person who queued the work.
        //
        // Handlers re-authorize before touching anything — DataSourceService requires that the
        // acting user *is* the job's actor, which is the right rule — but cron and Action Scheduler
        // run with no current user at all. Every queued job therefore failed the moment it reached
        // its handler, with "The actor does not have permission for this data operation." The
        // actor was authenticated when the job was queued and the id was recorded then; restoring
        // it for the length of the step is what "run this on their behalf" has to mean.
        $previousUser = get_current_user_id();
        if ($job->actorId > 0 && $previousUser !== $job->actorId) {
            wp_set_current_user($job->actorId);
        }

        try {
            $job = $handler->handle($job, self::BATCH_SIZE);
            $this->persist($job);

            if (! $job->terminal()) {
                $this->dispatcher->dispatch($job);
            }
        } catch (Throwable $exception) {
            $this->persist($job->fail($exception->getMessage(), new DateTimeImmutable('now')));
        } finally {
            if ($job->actorId > 0 && $previousUser !== $job->actorId) {
                wp_set_current_user($previousUser);
            }
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
