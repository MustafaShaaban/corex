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
use Corex\Notifications\NotificationAction;
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
            // The form's name in the title, because with several forms on a site "New form
            // submission" is the same sentence every time and identifies nothing (spec 074).
            // Occurrences are deduplicated per form, so the count on the item says how many.
            rendered: [
                'title' => sprintf(
                    /* translators: %s: the form slug. */
                    __('New submission on “%s”', 'corex'),
                    $event->flowSlug,
                ),
                // This used to read "Open the Submission Inbox to read and assign it" — an
                // instruction to go somewhere, in a record that could have taken you there. The
                // action below is that link, so the body says what is waiting instead of how to
                // reach it (spec 087, FR-014).
                'body'  => __('It is waiting in the inbox, unread and unassigned.', 'corex'),
            ],
            dedupKey: 'submission.new:' . $event->flowSlug,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_SUBMISSIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'flow',
            sourceId: (string) $event->flowId,
            // Deep-linked to this form's rows, not to the inbox at large: `corex_form` is the
            // parameter the inbox already reads for exactly this ("Forms & Flows links to the
            // submissions for this form" — inbox.js), so the link and the form filter cannot mean
            // different things.
            action: NotificationAction::to(
                'notifications.submission.new.action',
                add_query_arg(
                    ['page' => 'corex-submissions', 'corex_form' => (string) $event->flowId],
                    admin_url('admin.php'),
                ),
                CorexAbility::MANAGE_SUBMISSIONS,
                __('Open the Submission Inbox', 'corex'),
            ),
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
            // A failed notification email is diagnosed in Email Studio, which holds the attempt log
            // and the route that was meant to carry it. Gated on MANAGE_EMAIL, not on the
            // MANAGE_SUBMISSIONS this notification is addressed to: a submissions manager who
            // cannot open Email Studio is told the mail failed and is not offered a door that would
            // refuse them.
            action: NotificationAction::to(
                'notifications.submission.email_failed.action',
                add_query_arg(['page' => 'corex-email-studio'], admin_url('admin.php')),
                CorexAbility::MANAGE_EMAIL,
                __('Open Email Studio', 'corex'),
            ),
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
