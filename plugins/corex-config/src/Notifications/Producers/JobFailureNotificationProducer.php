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

    private function failure(JobFinishedEvent $event): Notification
    {
        return Notification::create(
            type: 'job.failed',
            category: NotificationCategory::JOBS,
            severity: NotificationSeverity::ERROR,
            sourceModule: 'jobs',
            titleKey: 'notifications.job.failed.title',
            messageKey: 'notifications.job.failed.body',
            rendered: [
                'title' => __('Background job failed', 'corex'),
                'body'  => sprintf(
                    /* translators: %s: the job kind identifier. */
                    __('A “%s” background job failed. Open the Jobs screen for details.', 'corex'),
                    $event->kind,
                ),
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
