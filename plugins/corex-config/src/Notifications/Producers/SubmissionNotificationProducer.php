<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Events\ListenerProvider;
use Corex\Forms\Submission\NotificationDelivery;
use Corex\Forms\Submission\SubmissionProcessedEvent;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Turns a processed visitor submission into notifications for the submissions managers. Always a
 * "new submission" (occurrence-merged per form so a busy form is one growing notification, not a
 * flood); plus — only when Phase A's typed delivery genuinely failed — a distinct email-failure
 * notification. That failure sits in the `email` category on purpose, so the channel policy (T021)
 * keeps a failed submission email from itself being emailed (FR-021 loop prevention).
 *
 * Dependency-aware: it produces nothing unless the forms module's submission event exists (FR-014).
 */
final class SubmissionNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'forms.submissions';
    }

    public function isAvailable(): bool
    {
        return class_exists(SubmissionProcessedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(SubmissionProcessedEvent::class, function (object $event): void {
            if ($event instanceof SubmissionProcessedEvent) {
                $this->handle($event);
            }
        });
    }

    private function handle(SubmissionProcessedEvent $event): void
    {
        $this->notifications->publish($this->newSubmission($event));

        if ($this->deliveryFailed($event->delivery)) {
            $this->notifications->publish($this->emailFailure($event));
        }
    }

    private function newSubmission(SubmissionProcessedEvent $event): Notification
    {
        return Notification::create(
            type: 'submission.new',
            category: NotificationCategory::SUBMISSIONS,
            severity: NotificationSeverity::ACTION,
            sourceModule: 'forms',
            titleKey: 'notifications.submission.new.title',
            messageKey: 'notifications.submission.new.body',
            rendered: [
                'title' => __('New form submission', 'corex'),
                'body'  => sprintf(
                    /* translators: %s: the form slug. */
                    __('A visitor submitted the “%s” form.', 'corex'),
                    $event->flowSlug,
                ),
            ],
            dedupKey: 'submission.new:' . $event->flowSlug,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_SUBMISSIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'flow',
            sourceId: (string) $event->flowId,
        );
    }

    private function emailFailure(SubmissionProcessedEvent $event): Notification
    {
        return Notification::create(
            type: 'submission.email_failed',
            category: NotificationCategory::EMAIL,
            severity: NotificationSeverity::ERROR,
            sourceModule: 'forms',
            titleKey: 'notifications.submission.email_failed.title',
            messageKey: 'notifications.submission.email_failed.body',
            rendered: [
                'title' => __('Submission notification email failed', 'corex'),
                'body'  => sprintf(
                    /* translators: %s: the form slug. */
                    __('The notification email for a “%s” submission could not be delivered.', 'corex'),
                    $event->flowSlug,
                ),
            ],
            dedupKey: 'submission.email_failure:' . $event->flowSlug,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_SUBMISSIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'flow',
            sourceId: (string) $event->flowId,
            metadata: ['delivery_status' => $event->delivery->status, 'reason' => $event->delivery->safeReason],
        );
    }

    /** An attempted delivery that did not succeed — never `not_attempted` (no binding) or legacy `unavailable`. */
    private function deliveryFailed(NotificationDelivery $delivery): bool
    {
        return ! $delivery->successful() && ! in_array($delivery->status, [
            NotificationDelivery::STATUS_NOT_ATTEMPTED,
            NotificationDelivery::STATUS_UNAVAILABLE,
        ], true);
    }
}
