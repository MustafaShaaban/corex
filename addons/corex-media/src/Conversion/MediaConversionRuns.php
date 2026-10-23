<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media\Conversion;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Jobs\BoundedJob;
use DateTimeImmutable;

/**
 * The latest library conversion (spec 119, US-SET-06): which job it is, the files that failed with
 * their reasons, and its start, cancellation and end in the activity log.
 *
 * Kept in one option, not autoloaded, holding at most {@see self::MOST_FAILURES} failures: a page
 * lists them, and a library where everything fails says so in its count.
 */
final class MediaConversionRuns implements MediaConversionLog
{
    public const OPTION = 'corex_media_conversion';

    public const MOST_FAILURES = 100;

    private const RETENTION = '+180 days';

    public function __construct(private readonly ?ActivityService $activity = null)
    {
    }

    public function jobId(): int
    {
        return (int) ($this->stored()['job_id'] ?? 0);
    }

    /** @return list<array{file:string,reason:string}> */
    public function failures(): array
    {
        $failures = [];
        foreach ((array) ($this->stored()['failures'] ?? []) as $failure) {
            if (is_array($failure)) {
                $failures[] = ['file' => (string) ($failure['file'] ?? ''), 'reason' => (string) ($failure['reason'] ?? '')];
            }
        }

        return $failures;
    }

    public function started(BoundedJob $job): void
    {
        update_option(self::OPTION, ['job_id' => $job->id, 'failures' => []], false);
        $this->record($job, 'media.conversion_started', ActivityEvent::OUTCOME_QUEUED, ['total' => $job->total]);
    }

    public function failed(int $jobId, array $failures): void
    {
        if ($jobId !== $this->jobId()) {
            return;
        }

        $all = array_slice([...$this->failures(), ...$failures], 0, self::MOST_FAILURES);
        update_option(self::OPTION, ['job_id' => $jobId, 'failures' => $all], false);
    }

    public function finished(BoundedJob $job): void
    {
        $this->record($job, 'media.conversion_finished', ActivityEvent::OUTCOME_SUCCESS, $this->counts($job));
    }

    public function cancelled(BoundedJob $job): void
    {
        $this->record($job, 'media.conversion_cancelled', ActivityEvent::OUTCOME_WARNING, $this->counts($job));
    }

    /** @return array{converted:int,skipped:int,failed:int} */
    private function counts(BoundedJob $job): array
    {
        return [
            'converted' => $job->succeeded,
            'skipped'   => max(0, $job->processed - $job->succeeded - $job->failed),
            'failed'    => $job->failed,
        ];
    }

    /** @param array<string,int> $context */
    private function record(BoundedJob $job, string $kind, string $outcome, array $context): void
    {
        if ($this->activity === null) {
            return;
        }

        $now  = new DateTimeImmutable('now');
        $user = get_userdata($job->actorId);

        try {
            $this->activity->record(
                actorId: $job->actorId,
                actorKind: ActivityEvent::ACTOR_USER,
                actorLabel: $user instanceof \WP_User ? (string) $user->display_name : '',
                area: ActivityEvent::AREA_SETTINGS,
                kind: $kind,
                targetType: 'job',
                targetId: (string) $job->id,
                targetLabel: __('Media library conversion to WebP', 'corex'),
                outcome: $outcome,
                summary: ['key' => $kind, 'args' => $context],
                context: $context,
                sensitivity: ActivityEvent::SENSITIVITY_PUBLIC_ADMIN,
                retentionUntil: $now->modify(self::RETENTION),
                occurredAt: $now,
            );
        } catch (\Throwable) {
            // The run has already started or ended; a record that cannot be kept does not change that.
        }
    }

    /** @return array<string,mixed> */
    private function stored(): array
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }
}
