<?php

/**
 * Unit tests for the submission notification producer (spec 072 US4: FR-013).
 *
 * Turns a processed visitor submission into notifications: always a "new submission" for the
 * submissions managers, and — only when Phase A's typed delivery genuinely failed — a distinct
 * email-failure notification in the `email` category so the loop guard (T021) can keep it off email.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\SubmissionNotificationProducer;
use Corex\Events\ListenerProvider;
use Corex\Forms\Submission\NotificationDelivery;
use Corex\Forms\Submission\SubmissionProcessedEvent;
use Corex\Mail\MailResult;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;

beforeEach(function () {
    // The producer renders user-facing strings through __(); stub the WP translation functions so
    // the unit suite stays headless (this single file run does not rely on another test defining them).
    Functions\stubTranslationFunctions();
});

/** A NotificationService that records every published notification. */
function recordingNotificationService(): NotificationService
{
    return new class implements NotificationService {
        /** @var list<Notification> */
        public array $published = [];

        public function publish(Notification $notification): Notification
        {
            $this->published[] = $notification;

            return $notification->withId(count($this->published));
        }

        public function resolve(string $dedupKey, string $reason): int
        {
            return 0;
        }

        public function forCurrentActor(NotificationQuery $query): array
        {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20];
        }

        public function unreadCountForCurrentActor(): int
        {
            return 0;
        }
    };
}

function dispatchProcessed(SubmissionNotificationProducer $producer, ListenerProvider $listeners, SubmissionProcessedEvent $event): void
{
    $producer->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }
}

function processedEvent(NotificationDelivery $delivery, string $slug = 'contact'): SubmissionProcessedEvent
{
    return new SubmissionProcessedEvent(
        submissionId: 42,
        flowId: 7,
        flowSlug: $slug,
        ownerId: 3,
        delivery: $delivery,
    );
}

it('publishes one new-submission notification when delivery succeeded', function () {
    $service = recordingNotificationService();
    $listeners = new ListenerProvider();

    dispatchProcessed(
        new SubmissionNotificationProducer($service, $listeners),
        $listeners,
        processedEvent(NotificationDelivery::wpMail(true, 'attempt-1')),
    );

    expect($service->published)->toHaveCount(1);
    $new = $service->published[0];
    expect($new->type)->toBe('submission.new')
        ->and($new->category)->toBe(NotificationCategory::SUBMISSIONS)
        ->and($new->dedupKey)->toBe('submission.new:contact')
        ->and($new->recipient->canBeSeenBy(99, fn (string $a): bool => $a === CorexAbility::MANAGE_SUBMISSIONS))->toBeTrue();
});

it('adds an email-failure notification in the email category when delivery failed', function () {
    $service = recordingNotificationService();
    $listeners = new ListenerProvider();

    dispatchProcessed(
        new SubmissionNotificationProducer($service, $listeners),
        $listeners,
        processedEvent(NotificationDelivery::wpMail(false, 'attempt-2')),
    );

    expect($service->published)->toHaveCount(2);
    $failure = $service->published[1];
    expect($failure->type)->toBe('submission.email_failed')
        ->and($failure->category)->toBe(NotificationCategory::EMAIL)
        ->and($failure->dedupKey)->toBe('submission.email_failure:contact');
});

it('does not raise an email-failure when no notification was attempted', function () {
    $service = recordingNotificationService();
    $listeners = new ListenerProvider();

    dispatchProcessed(
        new SubmissionNotificationProducer($service, $listeners),
        $listeners,
        processedEvent(NotificationDelivery::notAttempted('no_binding', 'No notification configured.')),
    );

    expect($service->published)->toHaveCount(1)
        ->and($service->published[0]->type)->toBe('submission.new');
});

it('is available only when the forms submission event exists', function () {
    $producer = new SubmissionNotificationProducer(recordingNotificationService(), new ListenerProvider());

    expect($producer->key())->toBe('forms.submissions')
        ->and($producer->isAvailable())->toBe(class_exists(SubmissionProcessedEvent::class));
});
