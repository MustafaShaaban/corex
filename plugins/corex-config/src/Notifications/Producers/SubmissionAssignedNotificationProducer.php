<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Config\Submissions\SubmissionAssignedEvent;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Notifies a user when a submission is assigned to them. Only person-level assignments raise a
 * notification — team/role ownership has no single addressee here — and a user assigning a submission
 * to themselves is not told what they just did. Dependency-aware via class_exists (FR-014).
 */
final class SubmissionAssignedNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'submissions.assignments';
    }

    public function isAvailable(): bool
    {
        return class_exists(SubmissionAssignedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(SubmissionAssignedEvent::class, function (object $event): void {
            if ($event instanceof SubmissionAssignedEvent && $this->notifiable($event)) {
                $this->notifications->publish($this->assigned($event));
            }
        });
    }

    private function notifiable(SubmissionAssignedEvent $event): bool
    {
        return $event->assigneeType === 'user'
            && (int) $event->assigneeKey > 0
            && (int) $event->assigneeKey !== $event->actorId;
    }

    private function assigned(SubmissionAssignedEvent $event): Notification
    {
        return Notification::create(
            type: 'submission.assigned',
            category: NotificationCategory::SUBMISSIONS,
            severity: NotificationSeverity::ACTION,
            sourceModule: 'submissions',
            titleKey: 'notifications.submission.assigned.title',
            messageKey: 'notifications.submission.assigned.body',
            rendered: [
                'title' => __('A submission was assigned to you', 'corex'),
                'body'  => __('You have been assigned a form submission to review.', 'corex'),
            ],
            dedupKey: 'submission.assigned:' . $event->submissionId . ':' . $event->assigneeKey,
            recipient: NotificationRecipient::forUser((int) $event->assigneeKey),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'submission',
            sourceId: (string) $event->submissionId,
        );
    }
}
