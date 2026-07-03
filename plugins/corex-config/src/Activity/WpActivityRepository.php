<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Activity;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Database\Schema\Migrator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * WordPress persistence adapter for the append-only CoreX activity stream.
 */
final class WpActivityRepository implements ActivityRepository
{
    private const MAX_PAGE_SIZE = 100;
    private const MAX_PRUNE_SIZE = 5000;

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function append(ActivityEvent $event): ActivityEvent
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->table(), [
            'event_uuid'      => $event->eventUuid,
            'occurred_at'     => $this->date($event->occurredAt),
            'actor_id'        => $event->actorId,
            'actor_kind'      => $event->actorKind,
            'actor_label'     => $event->actorLabel,
            'area'            => $event->area,
            'kind'            => $event->kind,
            'target_type'     => $event->targetType,
            'target_id'       => $event->targetId,
            'target_label'    => $event->targetLabel,
            'outcome'         => $event->outcome,
            'summary'         => $this->json($event->summary),
            'context_json'    => $this->json($event->context),
            'sensitivity'     => $event->sensitivity,
            'retention_until' => $this->date($event->retentionUntil),
        ]);

        if ($inserted === false || (int) $wpdb->insert_id < 1) {
            throw new RuntimeException('CoreX could not append the activity event.');
        }

        return $event->withId((int) $wpdb->insert_id);
    }

    public function find(int $id): ?ActivityEvent
    {
        global $wpdb;

        if ($id < 1) {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id),
            ARRAY_A,
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function query(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        global $wpdb;

        $clauses = [];
        $values  = [];
        $allowed = [
            'area'        => '%s',
            'kind'        => '%s',
            'outcome'     => '%s',
            'actor_id'    => '%d',
            'sensitivity' => '%s',
        ];

        foreach ($allowed as $key => $placeholder) {
            if (! array_key_exists($key, $filters) || $filters[$key] === '') {
                continue;
            }

            $clauses[] = sprintf('%s = %s', $key, $placeholder);
            $values[]  = $filters[$key];
        }

        foreach (['date_from' => '>=', 'date_to' => '<='] as $key => $operator) {
            if (! ($filters[$key] ?? null) instanceof DateTimeImmutable) {
                continue;
            }

            $clauses[] = sprintf('occurred_at %s %%s', $operator);
            $values[]  = $this->date($filters[$key]);
        }

        $page    = max(1, $page);
        $perPage = min(self::MAX_PAGE_SIZE, max(1, $perPage));
        $where   = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $sql     = 'SELECT * FROM ' . $this->table() . $where . ' ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d';
        $values[] = $perPage;
        $values[] = ($page - 1) * $perPage;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
    {
        global $wpdb;

        $limit = min(self::MAX_PRUNE_SIZE, max(1, $limit));
        $ids   = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . $this->table() . ' WHERE retention_until <= %s ORDER BY retention_until ASC, id ASC LIMIT %d',
            $this->date($now),
            $limit,
        ));
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));

        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $deleted      = $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . $this->table() . ' WHERE id IN (' . $placeholders . ')',
            $ids,
        ));

        if ($deleted === false) {
            throw new RuntimeException('CoreX could not prune expired activity events.');
        }

        return (int) $deleted;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): ActivityEvent
    {
        $summary = json_decode((string) $row['summary'], true);
        $context = json_decode((string) $row['context_json'], true);

        return new ActivityEvent(
            id: (int) $row['id'],
            eventUuid: (string) $row['event_uuid'],
            occurredAt: new DateTimeImmutable((string) $row['occurred_at'], new DateTimeZone('UTC')),
            actorId: (int) $row['actor_id'],
            actorKind: (string) $row['actor_kind'],
            actorLabel: (string) $row['actor_label'],
            area: (string) $row['area'],
            kind: (string) $row['kind'],
            targetType: (string) $row['target_type'],
            targetId: (string) $row['target_id'],
            targetLabel: (string) $row['target_label'],
            outcome: (string) $row['outcome'],
            summary: is_array($summary) ? $summary : [],
            context: is_array($context) ? $context : [],
            sensitivity: (string) $row['sensitivity'],
            retentionUntil: new DateTimeImmutable((string) $row['retention_until'], new DateTimeZone('UTC')),
        );
    }

    private function table(): string
    {
        return $this->migrator->fullName(ActivityTable::NAME);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        $encoded = wp_json_encode($value);

        if (! is_string($encoded)) {
            throw new RuntimeException('CoreX could not encode activity context.');
        }

        return $encoded;
    }
}
