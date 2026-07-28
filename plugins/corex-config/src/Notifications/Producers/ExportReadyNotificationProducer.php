<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\DataModels\DataExportJobHandler;
use Corex\Config\Submissions\SubmissionExportJobHandler;
use Corex\Events\ListenerProvider;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobFinishedEvent;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationAction;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Turns a completed export job into a personal "your export is ready" notification for the person who
 * ran it. Export kinds are *recognised* by the `.export` suffix convention (`submissions.export`,
 * `data.export`, and any future one), so a new export kind is surfaced without touching this class.
 *
 * Its *destination* is a different question and does name the two handlers, because the two screens
 * an export is collected from are gated by different abilities. A kind this class does not know is
 * announced without a link rather than sent to a screen that may be the wrong one (spec 087).
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
                'title' => __('Your export is ready to download', 'corex'),
                'body'  => __('It stays available until the export retention window ends.', 'corex'),
            ],
            dedupKey: 'export.ready:' . $event->jobId,
            recipient: NotificationRecipient::forUser($event->actorId),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'job',
            sourceId: (string) $event->jobId,
            metadata: ['job_kind' => $event->kind],
            action: $this->downloadAction($event->kind),
        );
    }

    /**
     * Where this export is collected from, or null when the kind is one we cannot place.
     *
     * An export is downloaded from the screen that produced it, and the two screens that produce
     * one are gated by different abilities — so the destination is chosen by kind rather than by a
     * single "exports live here" guess. An unrecognised kind gets no action at all: a link to the
     * wrong screen is worse than the notification's own text, which already says the file is ready
     * (spec 087, FR-014).
     */
    private function downloadAction(string $kind): ?NotificationAction
    {
        [$page, $ability] = match ($kind) {
            SubmissionExportJobHandler::KIND => ['corex-submissions', CorexAbility::MANAGE_SUBMISSIONS],
            DataExportJobHandler::KIND => ['corex-data-models', CorexAbility::MANAGE_DATA],
            default => [null, null],
        };

        if ($page === null || $ability === null) {
            return null;
        }

        return NotificationAction::to(
            'notifications.export.ready.action',
            add_query_arg(['page' => $page], admin_url('admin.php')),
            $ability,
            __('Download the export', 'corex'),
        );
    }
}
