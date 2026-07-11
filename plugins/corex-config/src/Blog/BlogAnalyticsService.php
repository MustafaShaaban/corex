<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * First-party, consent-aware Blog analytics counters.
 */
final class BlogAnalyticsService
{
    public function __construct(
        private readonly ReadingEventStore $events,
        private readonly string $hashSalt = 'corex-blog-analytics',
    ) {
    }

    public function recordView(
        int $postId,
        string $visitorKey,
        string $ipAddress,
        string $userAgent,
        bool $consented,
        DateTimeImmutable $occurredAt,
    ): ?ReadingEvent {
        return $this->record($postId, ReadingEvent::VIEW, $visitorKey, $ipAddress, $userAgent, $consented, $occurredAt);
    }

    public function recordRead(
        int $postId,
        string $visitorKey,
        string $ipAddress,
        string $userAgent,
        int $readingSeconds,
        bool $consented,
        DateTimeImmutable $occurredAt,
    ): ?ReadingEvent {
        return $this->record(
            $postId,
            ReadingEvent::READ,
            $visitorKey,
            $ipAddress,
            $userAgent,
            $consented,
            $occurredAt,
            max(0, $readingSeconds),
        );
    }

    public function recordShareClick(
        int $postId,
        string $visitorKey,
        string $ipAddress,
        string $userAgent,
        string $target,
        bool $consented,
        DateTimeImmutable $occurredAt,
    ): ?ReadingEvent {
        return $this->record(
            $postId,
            ReadingEvent::SHARE_CLICK,
            $visitorKey,
            $ipAddress,
            $userAgent,
            $consented,
            $occurredAt,
            shareTarget: $this->shareTarget($target),
        );
    }

    public function aggregate(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): BlogAnalyticsAggregate
    {
        $events = $this->events->between($postId, $since, $until);
        $visitorHashes = [];
        $readSeconds = [];
        $views = 0;
        $reads = 0;
        $shareClicks = 0;

        foreach ($events as $event) {
            $visitorHashes[$event->visitorHash] = true;
            if ($event->eventType === ReadingEvent::VIEW) {
                $views++;
            } elseif ($event->eventType === ReadingEvent::READ) {
                $reads++;
                $readSeconds[] = $event->readingSeconds ?? 0;
            } elseif ($event->eventType === ReadingEvent::SHARE_CLICK) {
                $shareClicks++;
            }
        }

        return new BlogAnalyticsAggregate(
            postId: $postId,
            views: $views,
            reads: $reads,
            shareClicks: $shareClicks,
            uniqueVisitors: count($visitorHashes),
            averageReadSeconds: $readSeconds === [] ? 0 : (int) round(array_sum($readSeconds) / count($readSeconds)),
        );
    }

    private function record(
        int $postId,
        string $eventType,
        string $visitorKey,
        string $ipAddress,
        string $userAgent,
        bool $consented,
        DateTimeImmutable $occurredAt,
        ?int $readingSeconds = null,
        ?string $shareTarget = null,
    ): ?ReadingEvent {
        if (! $consented || trim($visitorKey) === '') {
            return null;
        }

        $event = new ReadingEvent(
            postId: $postId,
            eventType: $eventType,
            visitorHash: $this->visitorHash($visitorKey, $ipAddress, $userAgent),
            occurredAt: $occurredAt,
            readingSeconds: $readingSeconds,
            shareTarget: $shareTarget,
        );
        $this->events->append($event);

        return $event;
    }

    private function visitorHash(string $visitorKey, string $ipAddress, string $userAgent): string
    {
        return hash('sha256', implode('|', [
            $this->hashSalt,
            trim($visitorKey),
            trim($ipAddress),
            trim($userAgent),
        ]));
    }

    private function shareTarget(string $target): string
    {
        $target = strtolower(trim($target));
        $target = preg_replace('/[^a-z0-9_-]+/', '-', $target) ?? '';

        return trim($target, '-') ?: 'unknown';
    }
}
