<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media\Conversion;

defined('ABSPATH') || exit;

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobService;
use Corex\Media\ImageCapability;
use Corex\Media\MediaRegenerationJob;
use Corex\Media\MediaRegenerationSource;
use Corex\Media\MediaSettings;
use Corex\Support\Facades\AdminDate;
use DateTimeImmutable;
use RuntimeException;

/**
 * Converts the images already in the Media library to WebP from Settings (spec 119, US-SET-06).
 *
 * Shared hosts have no command line, so `wp corex media regenerate-webp` was out of reach. This
 * starts the bounded job spec 068 registered for it, one run at a time; the open page advances it
 * a step at a time through the jobs runner's own action, and WP-Cron carries on when it is closed.
 */
final class MediaConversionService
{
    /** The runner's action (corex-config's `ActionSchedulerJobDispatcher::HOOK`), reached by name. */
    public const RUNNER_ACTION = 'corex_run_bounded_job';

    public function __construct(
        private readonly JobService $jobs,
        private readonly MediaConversionRuns $runs,
        private readonly MediaRegenerationSource $source,
        private readonly ImageCapability $capability,
        private readonly MediaSettings $settings,
    ) {
    }

    /** Why the library cannot be converted now, or empty when it can. */
    public function unavailableReason(): string
    {
        if (! $this->capability->canWebp()) {
            return __('This server cannot write WebP images, so the library cannot be converted.', 'corex');
        }

        if (! $this->settings->enabled) {
            return __('Conversion is off. Turn on "Convert uploads to WebP" and save, then convert the library.', 'corex');
        }

        return '';
    }

    /**
     * @return array{run:?array<string,mixed>,error:string} error is `conversion_unavailable`,
     *                                                      `conversion_running` or empty
     */
    public function start(int $actorId): array
    {
        if ($this->unavailableReason() !== '') {
            return ['run' => $this->status(), 'error' => 'conversion_unavailable'];
        }

        if ($this->active() !== null) {
            return ['run' => $this->status(), 'error' => 'conversion_running'];
        }

        try {
            $job = $this->jobs->enqueue(
                MediaRegenerationJob::KIND,
                $actorId,
                $this->source->total(),
                hash('sha256', MediaRegenerationJob::KIND),
                new DateTimeImmutable('now'),
            );
        } catch (RuntimeException) {
            return ['run' => $this->status(), 'error' => 'conversion_unavailable'];
        }

        $this->runs->started($job);

        return ['run' => $this->status(), 'error' => ''];
    }

    /** One step of the running conversion, taken while the page is open; then where it is. */
    public function advance(): ?array
    {
        $job = $this->active();
        if ($job !== null) {
            do_action(self::RUNNER_ACTION, $job->id);
        }

        return $this->status();
    }

    /** @return array<string,mixed>|null the run after cancelling, or null when nothing was running */
    public function cancel(): ?array
    {
        $job = $this->active();
        if ($job === null) {
            return null;
        }

        $cancelled = $this->jobs->cancel($job->id, new DateTimeImmutable('now'));
        if ($cancelled !== null) {
            $this->runs->cancelled($cancelled);
        }

        return $this->status();
    }

    /** @return array<string,mixed>|null the latest run as the page shows it, or null when there is none */
    public function status(): ?array
    {
        $job = $this->latest();

        return $job === null ? null : $this->view($job);
    }

    private function latest(): ?BoundedJob
    {
        $id = $this->runs->jobId();

        return $id > 0 ? $this->jobs->find($id) : null;
    }

    private function active(): ?BoundedJob
    {
        $job = $this->latest();

        return $job !== null && ! $job->terminal() ? $job : null;
    }

    /** @return array<string,mixed> */
    private function view(BoundedJob $job): array
    {
        $skipped = max(0, $job->processed - $job->succeeded - $job->failed);
        $counts  = sprintf(
            /* translators: 1: images converted. 2: images skipped. 3: images that failed. 4: images to check. */
            __('%1$s converted, %2$s skipped, %3$s failed, of %4$s images.', 'corex'),
            number_format_i18n($job->succeeded),
            number_format_i18n($skipped),
            number_format_i18n($job->failed),
            number_format_i18n($job->total),
        );
        $ended = $job->finishedAt === null ? '' : (string) AdminDate::full($job->finishedAt);

        return [
            'state'     => $job->state,
            'active'    => ! $job->terminal(),
            'total'     => $job->total,
            'processed' => $job->processed,
            'converted' => $job->succeeded,
            'skipped'   => $skipped,
            'failed'    => $job->failed,
            'ended_at'  => $ended,
            'failures'  => $this->runs->failures(),
            'summary'   => $this->summary($job, $counts, $ended),
        ];
    }

    private function summary(BoundedJob $job, string $counts, string $ended): string
    {
        return match ($job->state) {
            /* translators: 1: when the run ended. 2: its counts. */
            BoundedJob::STATE_COMPLETED => sprintf(__('Finished on %1$s: %2$s', 'corex'), $ended, $counts),
            /* translators: 1: when the run was cancelled. 2: its counts. */
            BoundedJob::STATE_CANCELLED => sprintf(__('Cancelled on %1$s: %2$s', 'corex'), $ended, $counts),
            BoundedJob::STATE_FAILED, BoundedJob::STATE_PARTIAL => sprintf(
                /* translators: 1: when the run stopped. 2: why. 3: its counts. */
                __('Stopped on %1$s: %2$s %3$s', 'corex'),
                $ended,
                $this->whyStopped($job),
                $counts,
            ),
            /* translators: %s: the run's counts so far. */
            default => sprintf(__('Converting: %s', 'corex'), $counts),
        };
    }

    /** The job runner says a job of an unknown kind has no handler: here that means conversion was turned off. */
    private function whyStopped(BoundedJob $job): string
    {
        return $this->unavailableReason() !== ''
            ? $this->unavailableReason()
            : (string) $job->errorSummary;
    }
}
