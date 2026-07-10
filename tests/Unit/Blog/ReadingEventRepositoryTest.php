<?php

/**
 * Unit tests for the Blog reading-event managed table and repository (Spec 068: T157, FR-096).
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Blog\ReadingEvent;
use Corex\Config\Blog\ReadingEventRepository;
use Corex\Config\Blog\ReadingEventTable;
use Corex\Database\Schema\Migrator;

defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('defines a privacy-preserving reading-event table with bounded analytics indexes', function () {
    $sql = (new ReadingEventTable())->schema()->createSql('wp_corex_blog_reading_events', 'DEFAULT CHARSET=utf8mb4');

    expect($sql)->toContain('post_id BIGINT NOT NULL')
        ->toContain('event_type VARCHAR(24) NOT NULL')
        ->toContain('visitor_hash VARCHAR(64) NOT NULL')
        ->toContain('occurred_at DATETIME NOT NULL')
        ->toContain('reading_seconds BIGINT NULL')
        ->toContain('share_target VARCHAR(64) NULL')
        ->toContain('retention_until DATETIME NOT NULL')
        ->not->toContain('ip_address')
        ->not->toContain('user_agent')
        ->not->toContain('visitor_key')
        ->toContain('KEY reading_events_post_window (post_id, occurred_at)')
        ->toContain('KEY reading_events_type (event_type)')
        ->toContain('KEY reading_events_visitor (visitor_hash)')
        ->toContain('KEY reading_events_retention (retention_until)');
});

it('publishes reading events to the managed data registry without raw visitor columns', function () {
    $managed = (new ReadingEventTable())->managed();

    expect($managed->name)->toBe(ReadingEventTable::NAME)
        ->and($managed->columnIds())->toBe([
            'occurred_at',
            'post_id',
            'event_type',
            'reading_seconds',
            'share_target',
            'retention_until',
        ]);
});

it('persists and hydrates reading events through the WordPress table adapter', function () {
    $previousWpdb = $GLOBALS['wpdb'] ?? null;
    $GLOBALS['wpdb'] = new CorexBlogReadingEventWpdbFake();

    try {
        $repository = new ReadingEventRepository(new Migrator());
        $event = new ReadingEvent(
            postId: 42,
            eventType: ReadingEvent::SHARE_CLICK,
            visitorHash: str_repeat('a', 64),
            occurredAt: new DateTimeImmutable('2026-07-08T15:30:00+02:00'),
            readingSeconds: 75,
            shareTarget: 'linkedin',
        );

        $repository->append($event);
        $rows = $GLOBALS['wpdb']->insertedRows;

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['table'])->toBe('wp_corex_blog_reading_events')
            ->and($rows[0]['data'])->toMatchArray([
                'post_id' => 42,
                'event_type' => ReadingEvent::SHARE_CLICK,
                'visitor_hash' => str_repeat('a', 64),
                'occurred_at' => '2026-07-08 13:30:00',
                'reading_seconds' => 75,
                'share_target' => 'linkedin',
                'retention_until' => '2027-01-04 13:30:00',
            ])
            ->and($rows[0]['data'])->not->toHaveKey('ip_address')
            ->and($rows[0]['data'])->not->toHaveKey('user_agent')
            ->and($rows[0]['data'])->not->toHaveKey('visitor_key');

        $events = $repository->between(
            42,
            new DateTimeImmutable('2026-07-08T00:00:00+00:00'),
            new DateTimeImmutable('2026-07-09T00:00:00+00:00'),
        );

        expect($events)->toHaveCount(1)
            ->and($events[0]->postId)->toBe(42)
            ->and($events[0]->eventType)->toBe(ReadingEvent::SHARE_CLICK)
            ->and($events[0]->visitorHash)->toBe(str_repeat('a', 64))
            ->and($events[0]->occurredAt->format(DATE_ATOM))->toBe('2026-07-08T13:30:00+00:00')
            ->and($events[0]->readingSeconds)->toBe(75)
            ->and($events[0]->shareTarget)->toBe('linkedin');
    } finally {
        $GLOBALS['wpdb'] = $previousWpdb;
    }
});

final class CorexBlogReadingEventWpdbFake
{
    public string $prefix = 'wp_';

    /** @var list<array{table:string,data:array<string,mixed>}> */
    public array $insertedRows = [];

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int|false
    {
        $this->insertedRows[] = ['table' => $table, 'data' => $data];

        return 1;
    }

    public function prepare(string $query, mixed ...$args): array
    {
        return ['query' => $query, 'args' => $args];
    }

    public function get_results(mixed $prepared, mixed $output = null): array
    {
        $args = is_array($prepared) ? $prepared['args'] : [];
        [$postId, $since, $until] = $args;

        $rows = array_values(array_filter(
            array_map(static fn (array $row): array => ['id' => 1, ...$row['data']], $this->insertedRows),
            static fn (array $row): bool =>
                (int) $row['post_id'] === (int) $postId
                && (string) $row['occurred_at'] >= (string) $since
                && (string) $row['occurred_at'] <= (string) $until,
        ));

        usort($rows, static fn (array $a, array $b): int =>
            [(string) $a['occurred_at'], (int) $a['id']] <=> [(string) $b['occurred_at'], (int) $b['id']]
        );

        return $rows;
    }
}
