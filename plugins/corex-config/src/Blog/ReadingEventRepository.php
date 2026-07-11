<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * WordPress persistence adapter for consented, pseudonymous Blog reading events.
 */
final class ReadingEventRepository implements ReadingEventStore
{
    private const RETENTION_DAYS = 180;

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function append(ReadingEvent $event): void
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->table(), [
            'post_id'         => $event->postId,
            'event_type'      => $event->eventType,
            'visitor_hash'    => $event->visitorHash,
            'occurred_at'     => $this->date($event->occurredAt),
            'reading_seconds' => $event->readingSeconds,
            'share_target'    => $event->shareTarget,
            'retention_until' => $this->date($event->occurredAt->modify('+' . self::RETENTION_DAYS . ' days')),
        ]);

        if ($inserted === false) {
            throw new RuntimeException('CoreX could not record the Blog reading event.');
        }
    }

    public function between(int $postId, DateTimeImmutable $since, DateTimeImmutable $until): array
    {
        global $wpdb;

        if ($postId < 1) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE post_id = %d AND occurred_at >= %s AND occurred_at <= %s ORDER BY occurred_at ASC, id ASC',
            $postId,
            $this->date($since),
            $this->date($until),
        ), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): ReadingEvent
    {
        return new ReadingEvent(
            postId: (int) $row['post_id'],
            eventType: (string) $row['event_type'],
            visitorHash: (string) $row['visitor_hash'],
            occurredAt: $this->fromDate((string) $row['occurred_at']),
            readingSeconds: $row['reading_seconds'] === null ? null : (int) $row['reading_seconds'],
            shareTarget: $row['share_target'] === null ? null : (string) $row['share_target'],
        );
    }

    private function table(): string
    {
        return $this->migrator->fullName(ReadingEventTable::NAME);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function fromDate(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }
}
