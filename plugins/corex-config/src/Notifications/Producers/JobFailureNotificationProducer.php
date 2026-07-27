<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Events\ListenerProvider;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobFinishedEvent;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Turns a failed background job into an operational notification for the operations managers (holders
 * of {@see CorexAbility::MANAGE_OPERATIONS}). Only failures are surfaced — a completed job is routine,
 * not a notification (FR-007).
 *
 * The job's raw error summary is deliberately not surfaced: unlike Phase A's mail result, a job's
 * error text carries no secret-free guarantee, so it stays on the job screen while the notification
 * says only that the job failed and where to look. Dependency-aware via class_exists (FR-014).
 */
final class JobFailureNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'jobs.failures';
    }

    public function isAvailable(): bool
    {
        return class_exists(JobFinishedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(JobFinishedEvent::class, function (object $event): void {
            if ($event instanceof JobFinishedEvent && $event->state === BoundedJob::STATE_FAILED) {
                $this->notifications->publish($this->failure($event));
            }
        });
    }

    /**
     * The job kind said as the piece of work it is.
     *
     * An unknown kind falls back to its identifier rather than to a generic phrase: a slug somebody
     * can search for beats "a background job" that names nothing.
     */
    private static function jobLabel(string $kind): string
    {
        return match ($kind) {
            'data.export'         => __('Your data export', 'corex'),
            'data.import'         => __('Your data import', 'corex'),
            'data.migration'      => __('The schema migration', 'corex'),
            'submissions.export'  => __('Your submissions export', 'corex'),
            default               => $kind,
        };
    }

    private function failure(JobFinishedEvent $event): Notification
    {
        return Notification::create(
            type: 'job.failed',
            category: NotificationCategory::JOBS,
            severity: NotificationSeverity::ERROR,
            sourceModule: 'jobs',
            titleKey: 'notifications.job.failed.title',
            messageKey: 'notifications.job.failed.body',
            // Outcome first, and named: "Background job failed" made every failure look the same
            // and told nobody which piece of work had stopped (spec 074, FR-4.7).
            rendered: [
                'title' => sprintf(
                    /* translators: %s: the human name of the job, e.g. "CSV export". */
                    __('%s did not finish', 'corex'),
                    self::jobLabel($event->kind),
                ),
                // Deliberately not the raw error summary: it is an unsanitised message from
                // whatever failed and has been seen to carry connection strings and credentials.
                // The reason belongs on the Operations screen, behind the ability that guards it.
                'body'  => __('Open Operations to see what happened and retry it.', 'corex'),
            ],
            dedupKey: 'job.failed:' . $event->jobId,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_OPERATIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'job',
            sourceId: (string) $event->jobId,
            metadata: ['job_kind' => $event->kind],
        );
    }
}
