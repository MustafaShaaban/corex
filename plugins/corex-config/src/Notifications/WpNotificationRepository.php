<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationRepository;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * WordPress persistence adapter for notifications and per-user state. Mirrors
 * {@see \Corex\Config\Activity\WpActivityRepository} — prepared statements, UTC handling, clamped
 * page size, select-then-delete pruning — and adds the dedup-keyed upsert, the per-user state join,
 * and the visibility filter every read applies.
 *
 * Visibility is enforced in PHP (the recipient predicate) over a bounded candidate set rather than in
 * SQL, because the ability check is a WordPress capability the database cannot evaluate. The SQL side
 * narrows and orders; the PHP side authorizes and paginates. Both are bounded.
 */
final class WpNotificationRepository implements NotificationRepository
{
    private const MAX_PAGE_SIZE = 100;
    private const MAX_PRUNE_SIZE = 5000;
    /** Upper bound on rows scanned for visibility before pagination — keeps reads bounded (FR-026). */
    private const MAX_CANDIDATES = 500;

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function upsertByDedupKey(Notification $notification): Notification
    {
        global $wpdb;

        $existing = $this->findByDedupKey($notification->dedupKey);
        if ($existing !== null) {
            // Record another occurrence and reopen if it had been resolved (FR-011, FR-010).
            $merged = $existing->withOccurrence($notification->latestOccurredAt);
            $wpdb->update($this->table(), [
                'occurrences'        => $merged->occurrences,
                'latest_occurred_at' => $this->date($merged->latestOccurredAt),
                'updated_at'         => $this->date(new DateTimeImmutable('now')),
                'resolved_at'        => null,
                'resolution_reason'  => null,
                'rendered_json'      => $this->json($notification->rendered),
            ], ['id' => $existing->id]);

            return $merged;
        }

        $now = $this->date(new DateTimeImmutable('now'));
        $inserted = $wpdb->insert($this->table(), [
            'uuid'               => $notification->uuid,
            'type'               => $notification->type,
            'category'           => $notification->category,
            'severity'           => $notification->severity,
            'source_module'      => $notification->sourceModule,
            'source_type'        => $notification->sourceType,
            'source_id'          => $notification->sourceId,
            'title_key'          => $notification->titleKey,
            'message_key'        => $notification->messageKey,
            'rendered_json'      => $this->json($notification->rendered),
            'dedup_key'          => $notification->dedupKey,
            'occurrences'        => $notification->occurrences,
            'first_occurred_at'  => $this->date($notification->firstOccurredAt),
            'latest_occurred_at' => $this->date($notification->latestOccurredAt),
            'created_at'         => $now,
            'updated_at'         => $now,
            'expires_at'         => $notification->expiresAt !== null ? $this->date($notification->expiresAt) : null,
            'resolved_at'        => null,
            'resolution_reason'  => null,
            'environment'        => $notification->environment,
            'actor_id'           => $notification->actorId,
            'recipient_json'     => $this->json($notification->recipient->toArray()),
            'action_json'        => $notification->action !== null ? $this->json($notification->action->toArray()) : null,
            'metadata_json'      => $this->json($notification->metadata),
        ]);

        if ($inserted === false || (int) $wpdb->insert_id < 1) {
            throw new RuntimeException('CoreX could not store the notification.');
        }

        return $notification->withId((int) $wpdb->insert_id);
    }

    public function find(int $id): ?Notification
    {
        global $wpdb;

        if ($id < 1) {
            return null;
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function queryForActor(NotificationQuery $query, int $actorId, callable $userCan): array
    {
        $candidates = $this->candidates($query);
        $visible = array_values(array_filter(
            $candidates,
            fn (Notification $n): bool => $n->recipient->canBeSeenBy($actorId, $userCan),
        ));

        $total = count($visible);
        $offset = ($query->page - 1) * $query->perPage;
        $pageItems = array_slice($visible, $offset, $query->perPage);
        $state = $this->stateFor(array_map(static fn (Notification $n): int => (int) $n->id, $pageItems), $actorId);

        return [
            'items' => array_map(fn (Notification $n): array => $this->present($n, $state), $pageItems),
            'total' => $total,
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];
    }

    public function unreadCountForActor(int $actorId, callable $userCan): int
    {
        // Unread = visible, not resolved, and no read/dismissed state row for this user.
        $candidates = $this->candidates(NotificationQuery::fromRequest(['unread_only' => true], 1, self::MAX_CANDIDATES));
        $ids = array_map(static fn (Notification $n): int => (int) $n->id, $candidates);
        $state = $this->stateFor($ids, $actorId);

        $count = 0;
        foreach ($candidates as $n) {
            if (! $n->recipient->canBeSeenBy($actorId, $userCan)) {
                continue;
            }
            $s = $state[(int) $n->id] ?? null;
            if ($s === null || ($s['read_at'] === null && $s['dismissed_at'] === null)) {
                $count++;
            }
        }

        return $count;
    }

    public function markRead(int $notificationId, int $actorId): bool
    {
        return $this->setState($notificationId, $actorId, ['read_at' => $this->date(new DateTimeImmutable('now'))]);
    }

    public function markUnread(int $notificationId, int $actorId): bool
    {
        return $this->setState($notificationId, $actorId, ['read_at' => null]);
    }

    public function markAllVisibleRead(int $actorId, callable $userCan): int
    {
        $candidates = $this->candidates(NotificationQuery::fromRequest([], 1, self::MAX_CANDIDATES));
        $marked = 0;
        foreach ($candidates as $n) {
            if ($n->recipient->canBeSeenBy($actorId, $userCan) && $this->markRead((int) $n->id, $actorId)) {
                $marked++;
            }
        }

        return $marked;
    }

    public function dismiss(int $notificationId, int $actorId): bool
    {
        return $this->setState($notificationId, $actorId, ['dismissed_at' => $this->date(new DateTimeImmutable('now'))]);
    }

    public function snooze(int $notificationId, int $actorId, DateTimeImmutable $until): bool
    {
        return $this->setState($notificationId, $actorId, ['snoozed_until' => $this->date($until)]);
    }

    public function resolveByDedupKey(string $dedupKey, string $reason, DateTimeImmutable $at): int
    {
        global $wpdb;

        return (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . $this->table() . ' SET resolved_at = %s, resolution_reason = %s, updated_at = %s WHERE dedup_key = %s AND resolved_at IS NULL',
            $this->date($at),
            $reason,
            $this->date(new DateTimeImmutable('now')),
            $dedupKey,
        ));
    }

    public function reopenByDedupKey(string $dedupKey): int
    {
        global $wpdb;

        return (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . $this->table() . ' SET resolved_at = NULL, resolution_reason = NULL, updated_at = %s WHERE dedup_key = %s',
            $this->date(new DateTimeImmutable('now')),
            $dedupKey,
        ));
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff, int $limit = 500): int
    {
        global $wpdb;

        $limit = min(self::MAX_PRUNE_SIZE, max(1, $limit));
        // Prune resolved conditions, or anything past its expiry, older than the cutoff.
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . $this->table()
            . ' WHERE latest_occurred_at <= %s AND (resolved_at IS NOT NULL OR (expires_at IS NOT NULL AND expires_at <= %s))'
            . ' ORDER BY latest_occurred_at ASC, id ASC LIMIT %d',
            $this->date($cutoff),
            $this->date($cutoff),
            $limit,
        ));
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->stateTable() . ' WHERE notification_id IN (' . $placeholders . ')', $ids));
        $deleted = $wpdb->query($wpdb->prepare('DELETE FROM ' . $this->table() . ' WHERE id IN (' . $placeholders . ')', $ids));

        if ($deleted === false) {
            throw new RuntimeException('CoreX could not prune notifications.');
        }

        return (int) $deleted;
    }

    // ---- internals -------------------------------------------------------

    private function findByDedupKey(string $dedupKey): ?Notification
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->table() . ' WHERE dedup_key = %s', $dedupKey), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * The bounded candidate set for a query: SQL narrows by category/severity/source and (for
     * unread_only) excludes resolved, orders newest-first, and caps the scan. Visibility is applied
     * in PHP afterwards.
     *
     * @return list<Notification>
     */
    private function candidates(NotificationQuery $query): array
    {
        global $wpdb;

        $clauses = [];
        $values = [];
        foreach (['category' => $query->category, 'severity' => $query->severity, 'source_module' => $query->sourceModule] as $col => $val) {
            if ($val !== null) {
                $clauses[] = "$col = %s";
                $values[] = $val;
            }
        }
        if ($query->unreadOnly) {
            $clauses[] = 'resolved_at IS NULL';
        }
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $values[] = self::MAX_CANDIDATES;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . $this->table() . $where . ' ORDER BY latest_occurred_at DESC, id DESC LIMIT %d', $values),
            ARRAY_A,
        );

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    /**
     * @param list<int> $notificationIds
     * @return array<int,array{read_at:?string,dismissed_at:?string,snoozed_until:?string,acknowledged_at:?string}>
     */
    private function stateFor(array $notificationIds, int $actorId): array
    {
        global $wpdb;

        $notificationIds = array_values(array_filter(array_map('intval', $notificationIds)));
        if ($notificationIds === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($notificationIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT notification_id, read_at, dismissed_at, snoozed_until, acknowledged_at FROM ' . $this->stateTable()
            . ' WHERE user_id = %d AND notification_id IN (' . $placeholders . ')',
            array_merge([$actorId], $notificationIds),
        ), ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(int) $row['notification_id']] = [
                'read_at' => $row['read_at'],
                'dismissed_at' => $row['dismissed_at'],
                'snoozed_until' => $row['snoozed_until'],
                'acknowledged_at' => $row['acknowledged_at'],
            ];
        }

        return $out;
    }

    /**
     * Upsert one field-set into the per-user state row. Fails (returns false) when the actor cannot
     * see the notification — the visibility check the whole feature depends on (FR-002).
     *
     * @param array<string,?string> $fields
     */
    private function setState(int $notificationId, int $actorId, array $fields): bool
    {
        global $wpdb;

        $notification = $this->find($notificationId);
        if ($notification === null || ! $notification->recipient->canBeSeenBy($actorId, static fn (string $ability): bool => current_user_can($ability))) {
            return false;
        }

        $now = $this->date(new DateTimeImmutable('now'));
        $exists = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . $this->stateTable() . ' WHERE notification_id = %d AND user_id = %d',
            $notificationId,
            $actorId,
        ));

        if ($exists > 0) {
            $wpdb->update($this->stateTable(), array_merge($fields, ['updated_at' => $now]), ['id' => $exists]);
        } else {
            $wpdb->insert($this->stateTable(), array_merge([
                'notification_id' => $notificationId,
                'user_id' => $actorId,
                'read_at' => null,
                'dismissed_at' => null,
                'snoozed_until' => null,
                'acknowledged_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $fields));
        }

        return true;
    }

    /**
     * @param array<int,array<string,?string>> $state
     * @return array<string,mixed>
     */
    private function present(Notification $notification, array $state): array
    {
        $s = $state[(int) $notification->id] ?? null;
        $data = $notification->toArray();
        $data['user_state'] = [
            'read' => $s !== null && $s['read_at'] !== null,
            'dismissed' => $s !== null && $s['dismissed_at'] !== null,
            'snoozed_until' => $s['snoozed_until'] ?? null,
        ];

        return $data;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): Notification
    {
        return Notification::fromArray([
            'id' => (int) $row['id'],
            'uuid' => (string) $row['uuid'],
            'type' => (string) $row['type'],
            'category' => (string) $row['category'],
            'severity' => (string) $row['severity'],
            'source_module' => (string) $row['source_module'],
            'source_type' => $row['source_type'],
            'source_id' => $row['source_id'],
            'title_key' => (string) $row['title_key'],
            'message_key' => (string) $row['message_key'],
            'rendered' => json_decode((string) $row['rendered_json'], true),
            'dedup_key' => (string) $row['dedup_key'],
            'occurrences' => (int) $row['occurrences'],
            'first_occurred_at' => $this->atom($row['first_occurred_at']),
            'latest_occurred_at' => $this->atom($row['latest_occurred_at']),
            'expires_at' => $row['expires_at'] !== null ? $this->atom($row['expires_at']) : null,
            'resolved_at' => $row['resolved_at'] !== null ? $this->atom($row['resolved_at']) : null,
            'resolution_reason' => $row['resolution_reason'],
            'environment' => $row['environment'],
            'actor_id' => $row['actor_id'] !== null ? (int) $row['actor_id'] : null,
            'recipient' => json_decode((string) $row['recipient_json'], true),
            'action' => $row['action_json'] !== null ? json_decode((string) $row['action_json'], true) : null,
            'metadata' => json_decode((string) $row['metadata_json'], true),
        ]);
    }

    private function table(): string
    {
        return $this->migrator->fullName(NotificationTable::NAME);
    }

    private function stateTable(): string
    {
        return $this->migrator->fullName(NotificationUserStateTable::NAME);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Convert a stored `Y-m-d H:i:s` UTC datetime to the ATOM string {@see Notification::fromArray} expects. */
    private function atom(string $stored): string
    {
        return (new DateTimeImmutable($stored, new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    /** @param array<mixed> $value */
    private function json(array $value): string
    {
        $encoded = wp_json_encode($value);
        if (! is_string($encoded)) {
            throw new RuntimeException('CoreX could not encode notification data.');
        }

        return $encoded;
    }
}
