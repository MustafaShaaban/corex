<?php

/**
 * Unit tests for Blog Pro author analytics projections.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Blog\AuthorAnalyticsService;
use Corex\Config\Blog\BlogAnalyticsService;
use Corex\Config\Blog\ReadingEvent;
use Corex\Config\Blog\ReadingEventStore;

it('projects authors with real post counts views reads and engagement', function () {
    Functions\when('__')->returnArg();
    Functions\when('get_users')->justReturn([
        (object) ['ID' => 7, 'display_name' => 'Mina Author', 'roles' => ['author']],
    ]);
    Functions\when('count_user_posts')->justReturn(2);
    Functions\when('get_posts')->justReturn([42, 43]);

    $store = new class implements ReadingEventStore {
        /** @return list<ReadingEvent> */
        public function between(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): array
        {
            $hash = str_repeat((string) ($postId % 10), 64);

            return match ($postId) {
                42 => [
                    new ReadingEvent($postId, ReadingEvent::VIEW, $hash, $since),
                    new ReadingEvent($postId, ReadingEvent::READ, $hash, $since, readingSeconds: 60),
                ],
                43 => [
                    new ReadingEvent($postId, ReadingEvent::VIEW, $hash, $since),
                ],
                default => [],
            };
        }

        public function append(ReadingEvent $event): void
        {
        }
    };

    $authors = (new AuthorAnalyticsService(new BlogAnalyticsService($store)))->authors(
        new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        new DateTimeImmutable('2026-07-08T00:00:00+00:00'),
    );

    expect($authors)->toHaveCount(1)
        ->and($authors[0]['name'])->toBe('Mina Author')
        ->and($authors[0]['role'])->toBe('author')
        ->and($authors[0]['post_count'])->toBe(2)
        ->and($authors[0]['views'])->toBe(2)
        ->and($authors[0]['reads'])->toBe(1)
        ->and($authors[0]['engagement'])->toBe(50.0);
});
