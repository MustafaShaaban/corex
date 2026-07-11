<?php

/**
 * Unit tests for first-party Blog analytics counters (spec 068: FR-096–FR-100).
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Corex\Config\Blog\BlogAnalyticsService;
use Corex\Config\Blog\ReadingEvent;
use Corex\Config\Blog\ReadingEventStore;

beforeEach(function () {
    $this->store = new class implements ReadingEventStore {
        /** @var list<ReadingEvent> */
        public array $events = [];

        public function append(ReadingEvent $event): void
        {
            $this->events[] = $event;
        }

        public function between(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): array
        {
            return array_values(array_filter(
                $this->events,
                static fn (ReadingEvent $event): bool =>
                    $event->postId === $postId
                    && $event->occurredAt >= $since
                    && $event->occurredAt <= $until,
            ));
        }
    };

    $this->service = new BlogAnalyticsService($this->store, 'unit-salt');
    $this->now = new DateTimeImmutable('2026-07-07T12:00:00+00:00');
});

it('records consented first-party views without storing raw identifiers', function () {
    $event = $this->service->recordView(
        postId: 42,
        visitorKey: 'session-abc',
        ipAddress: '203.0.113.8',
        userAgent: 'Mozilla/5.0',
        consented: true,
        occurredAt: $this->now,
    );

    expect($event)->toBeInstanceOf(ReadingEvent::class)
        ->and($event->eventType)->toBe(ReadingEvent::VIEW)
        ->and($event->visitorHash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($this->store->events)->toHaveCount(1)
        ->and(property_exists($event, 'ipAddress'))->toBeFalse()
        ->and(property_exists($event, 'userAgent'))->toBeFalse()
        ->and(property_exists($event, 'visitorKey'))->toBeFalse();
});

it('drops analytics events when consent is absent', function () {
    $event = $this->service->recordRead(
        postId: 42,
        visitorKey: 'session-abc',
        ipAddress: '203.0.113.8',
        userAgent: 'Mozilla/5.0',
        readingSeconds: 90,
        consented: false,
        occurredAt: $this->now,
    );

    expect($event)->toBeNull()
        ->and($this->store->events)->toBe([]);
});

it('aggregates views reads share clicks unique visitors and average read time', function () {
    $this->service->recordView(42, 'reader-a', '203.0.113.8', 'Browser A', true, $this->now);
    $this->service->recordView(42, 'reader-a', '203.0.113.8', 'Browser A', true, $this->now->modify('+1 minute'));
    $this->service->recordRead(42, 'reader-a', '203.0.113.8', 'Browser A', 60, true, $this->now->modify('+2 minutes'));
    $this->service->recordShareClick(42, 'reader-b', '198.51.100.7', 'Browser B', 'mastodon', true, $this->now->modify('+3 minutes'));
    $this->service->recordView(99, 'other-post', '198.51.100.8', 'Browser C', true, $this->now->modify('+4 minutes'));

    $aggregate = $this->service->aggregate(
        postId: 42,
        since: $this->now->modify('-1 minute'),
        until: $this->now->modify('+10 minutes'),
    );

    expect($aggregate->postId)->toBe(42)
        ->and($aggregate->views)->toBe(2)
        ->and($aggregate->reads)->toBe(1)
        ->and($aggregate->shareClicks)->toBe(1)
        ->and($aggregate->uniqueVisitors)->toBe(2)
        ->and($aggregate->averageReadSeconds)->toBe(60);
});
