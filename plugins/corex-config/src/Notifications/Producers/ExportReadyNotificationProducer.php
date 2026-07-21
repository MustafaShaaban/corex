<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

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
 * Turns a completed export job into a personal "your export is ready" notification for the person who
 * ran it. Export kinds are recognised by the `.export` suffix convention (`submissions.export`,
 * `data.export`, and any future one) so no coupling to the individual handlers is needed.
 *
 * Only completed exports are surfaced here; failures are the {@see JobFailureNotificationProducer}'s
 * responsibility. Dependency-aware via class_exists (FR-014).
 */
final class ExportReadyNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'jobs.exports';
    }

    public function isAvailable(): bool
    {
        return class_exists(JobFinishedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(JobFinishedEvent::class, function (object $event): void {
            if ($event instanceof JobFinishedEvent && $this->isCompletedExport($event)) {
                $this->notifications->publish($this->exportReady($event));
            }
        });
    }

    private function isCompletedExport(JobFinishedEvent $event): bool
    {
        return $event->state === BoundedJob::STATE_COMPLETED && str_ends_with($event->kind, '.export');
    }

    private function exportReady(JobFinishedEvent $event): Notification
    {
        return Notification::create(
            type: 'export.ready',
            category: NotificationCategory::IMPORTS_EXPORTS,
            severity: NotificationSeverity::INFORMATION,
            sourceModule: 'jobs',
            titleKey: 'notifications.export.ready.title',
            messageKey: 'notifications.export.ready.body',
            rendered: [
                'title' => __('Your export is ready', 'corex'),
                'body'  => __('The export you requested has finished and is ready to download.', 'corex'),
            ],
            dedupKey: 'export.ready:' . $event->jobId,
            recipient: NotificationRecipient::forUser($event->actorId),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'job',
            sourceId: (string) $event->jobId,
            metadata: ['job_kind' => $event->kind],
        );
    }
}
