<?php

/**
 * "Nothing recorded" is not "recorded nothing" (spec 075, FR-4).
 *
 * The old panel showed four large zeros under "First-party reading signals". A zero there could mean
 * the post has never been opened, or that analytics has never seen this post at all — and those call
 * for opposite reactions. A count cannot carry the difference, so the aggregate says which.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Corex\Config\Blog\BlogAnalyticsService;
use Corex\Config\Blog\ReadingEvent;
use Corex\Config\Blog\ReadingEventStore;
use DateTimeImmutable;
use DateTimeZone;

/** An in-memory store: these assert the aggregate's arithmetic, not WordPress. */
function hasDataStore(array $events): ReadingEventStore
{
    return new class ($events) implements ReadingEventStore {
        /** @param list<ReadingEvent> $events */
        public function __construct(private readonly array $events)
        {
        }

        public function append(ReadingEvent $event): void
        {
        }

        public function between(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): array
        {
            return $this->events;
        }
    };
}

function hasDataEvent(string $type, ?int $seconds = null): ReadingEvent
{
    return new ReadingEvent(
        postId: 7,
        eventType: $type,
        // The event object validates this is a real SHA-256, so it cannot be a readable placeholder.
        visitorHash: str_repeat('a', 64),
        occurredAt: new DateTimeImmutable('2026-07-27 10:00:00', new DateTimeZone('UTC')),
        readingSeconds: $seconds,
    );
}

function hasDataAggregate(array $events)
{
    $service = new BlogAnalyticsService(hasDataStore($events));

    return $service->aggregate(
        7,
        new DateTimeImmutable('2026-07-01', new DateTimeZone('UTC')),
        new DateTimeImmutable('2026-07-31', new DateTimeZone('UTC')),
    );
}

it('reports no data for a post analytics has never seen', function () {
    $aggregate = hasDataAggregate([]);

    expect($aggregate->hasData)->toBeFalse()
        ->and($aggregate->views)->toBe(0)
        ->and($aggregate->reads)->toBe(0);
});

it('reports data even when every counted metric is zero', function () {
    // The case a bare zero cannot express: something happened here — a share click — and yet views and
    // reads are both 0. Showing "no data yet" would be a lie, and showing a plain 0 loses that a real
    // visitor did something.
    $aggregate = hasDataAggregate([hasDataEvent(ReadingEvent::SHARE_CLICK)]);

    expect($aggregate->hasData)->toBeTrue()
        ->and($aggregate->views)->toBe(0)
        ->and($aggregate->reads)->toBe(0)
        ->and($aggregate->shareClicks)->toBe(1);
});

it('reports data for an ordinary post that has been read', function () {
    $aggregate = hasDataAggregate([
        hasDataEvent(ReadingEvent::VIEW),
        hasDataEvent(ReadingEvent::READ, 90),
    ]);

    expect($aggregate->hasData)->toBeTrue()
        ->and($aggregate->views)->toBe(1)
        ->and($aggregate->reads)->toBe(1)
        ->and($aggregate->averageReadSeconds)->toBe(90);
});
