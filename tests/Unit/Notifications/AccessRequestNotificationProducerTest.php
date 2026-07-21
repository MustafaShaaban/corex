<?php

/**
 * Unit tests for the access-request notification producer (spec 072 US4: FR-013).
 *
 * A pending access request becomes an actionable notification for the access managers. Each request
 * is distinct (unique dedup key) — access requests are individually decided, never merged.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AccessRequestedEvent;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\AccessRequestNotificationProducer;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

/** A NotificationService that records every published notification. */
function accessRecordingService(): NotificationService
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

it('publishes an access-request notification to the access managers', function () {
    $service = accessRecordingService();
    $listeners = new ListenerProvider();
    (new AccessRequestNotificationProducer($service, $listeners))->register();

    $event = new AccessRequestedEvent(
        requestId: 55,
        requesterId: 12,
        requesterName: 'Dana Requester',
        abilityKey: CorexAbility::MANAGE_SUBMISSIONS,
        areaKey: null,
    );
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('access.request')
        ->and($note->category)->toBe(NotificationCategory::ACCESS)
        ->and($note->dedupKey)->toBe('access.request:55')
        ->and($note->recipient->canBeSeenBy(99, fn (string $a): bool => $a === CorexAbility::MANAGE_ACCESS))->toBeTrue()
        ->and($note->recipient->canBeSeenBy(99, fn (string $a): bool => false))->toBeFalse();
});

it('keys each request distinctly so two requests are two notifications', function () {
    $service = accessRecordingService();
    $listeners = new ListenerProvider();
    (new AccessRequestNotificationProducer($service, $listeners))->register();

    foreach ([55, 56] as $id) {
        $event = new AccessRequestedEvent($id, 12, 'Dana', null, 'submissions');
        foreach ($listeners->listenersFor($event) as $listener) {
            $listener($event);
        }
    }

    expect($service->published)->toHaveCount(2)
        ->and($service->published[0]->dedupKey)->toBe('access.request:55')
        ->and($service->published[1]->dedupKey)->toBe('access.request:56');
});

it('is available only when the access request event exists', function () {
    $producer = new AccessRequestNotificationProducer(accessRecordingService(), new ListenerProvider());

    expect($producer->key())->toBe('access.requests')
        ->and($producer->isAvailable())->toBe(class_exists(AccessRequestedEvent::class));
});
