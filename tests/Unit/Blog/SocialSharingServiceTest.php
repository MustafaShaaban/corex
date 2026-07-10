<?php

/**
 * Unit tests for Blog Pro social sharing controls and share-click analytics.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Blog\BlogAnalyticsService;
use Corex\Config\Blog\ReadingEvent;
use Corex\Config\Blog\ReadingEventStore;
use Corex\Config\Blog\SocialSharingService;
use Corex\Config\Blog\SocialSharingSettings;
use Corex\Config\Blog\SocialSharingSettingsStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('builds configured share controls with labels and encoded native post URLs', function () {
    $service = new SocialSharingService(
        new BlogAnalyticsService(new CorexSocialSharingReadingStoreFake()),
        new CorexSocialSharingSettingsStoreFake(new SocialSharingSettings(
            enabledPlatforms: ['x', 'linkedin', 'copy_link'],
            logClicks: true,
        )),
    );

    $controls = $service->controls(
        postId: 42,
        permalink: 'https://example.test/blog/corex launch',
        title: 'CoreX Launch & Roadmap',
    );

    expect(array_column($controls, 'target'))->toBe(['x', 'linkedin', 'copy_link'])
        ->and(array_column($controls, 'label'))->toBe(['X', 'LinkedIn', 'Copy link'])
        ->and($controls[0]['url'])->toContain('https%3A%2F%2Fexample.test%2Fblog%2Fcorex%20launch')
        ->and($controls[0]['url'])->toContain('CoreX%20Launch%20%26%20Roadmap')
        ->and($controls[2]['url'])->toBe('https://example.test/blog/corex launch');
});

it('logs share clicks through consent-aware first-party analytics only when enabled', function () {
    $events = new CorexSocialSharingReadingStoreFake();
    $enabled = new SocialSharingService(
        new BlogAnalyticsService($events, 'unit-salt'),
        new CorexSocialSharingSettingsStoreFake(new SocialSharingSettings(['linkedin'], true)),
    );
    $disabled = new SocialSharingService(
        new BlogAnalyticsService($events, 'unit-salt'),
        new CorexSocialSharingSettingsStoreFake(new SocialSharingSettings(['linkedin'], false)),
    );

    $event = $enabled->recordShareClick(
        postId: 42,
        target: 'LinkedIn!!',
        visitorKey: 'session-abc',
        ipAddress: '203.0.113.8',
        userAgent: 'Mozilla/5.0',
        consented: true,
        occurredAt: new DateTimeImmutable('2026-07-08T10:00:00+00:00'),
    );
    $ignored = $disabled->recordShareClick(
        postId: 42,
        target: 'linkedin',
        visitorKey: 'session-abc',
        ipAddress: '203.0.113.8',
        userAgent: 'Mozilla/5.0',
        consented: true,
        occurredAt: new DateTimeImmutable('2026-07-08T10:01:00+00:00'),
    );

    expect($event)->toBeInstanceOf(ReadingEvent::class)
        ->and($event?->eventType)->toBe(ReadingEvent::SHARE_CLICK)
        ->and($event?->shareTarget)->toBe('linkedin')
        ->and($ignored)->toBeNull()
        ->and($events->events)->toHaveCount(1);
});

final class CorexSocialSharingSettingsStoreFake implements SocialSharingSettingsStore
{
    public function __construct(private readonly SocialSharingSettings $settings)
    {
    }

    public function current(): SocialSharingSettings
    {
        return $this->settings;
    }
}

final class CorexSocialSharingReadingStoreFake implements ReadingEventStore
{
    /** @var list<ReadingEvent> */
    public array $events = [];

    public function append(ReadingEvent $event): void
    {
        $this->events[] = $event;
    }

    public function between(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        return [];
    }
}
