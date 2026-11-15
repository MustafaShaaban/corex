<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Database\Schema\Migrator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * WordPress persistence adapter for keyed login attempt and lockout evidence.
 *
 * A release, from the screen or from the recovery command, is one `UPDATE` whose condition is "still
 * active and not released already", so it needs no lock of its own: two releases at once end each
 * row once, and the one that ends nothing learns it from its count (spec 124, FR-017).
 */
final class WpLoginAttemptStore implements LoginAttemptStore, LoginLockoutStore, LoginLockoutReader, LoginLockoutReleases
{
    private const MAX_PRUNE_SIZE = 5000;

    private const MAX_LOCKOUT_READ = 100;

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function record(LoginAttemptRecord $record): void
    {
        global $wpdb;

        $inserted = $wpdb->insert($this->table(), [
            'occurred_at'     => $this->date($record->occurredAt),
            'identity_hash'   => $record->identityHash,
            'network_hash'    => $record->networkHash,
            'outcome'         => $record->outcome,
            'reason_code'     => $record->reasonCode,
            'user_id'         => $record->userId,
            'locked_until'    => $record->lockedUntil === null ? null : $this->date($record->lockedUntil),
            'retention_until' => $this->date($record->retentionUntil),
        ]);

        if ($inserted === false) {
            throw new RuntimeException('CoreX could not record the login attempt.');
        }
    }

    /**
     * Failures since `$since` that still count: those after the latest release of a lockout stored
     * under any of the attempt's pairs are the only ones that do (spec 124, FR-017). A released
     * lockout's end is the moment of its release, so the attempts that led to it stay recorded and
     * no longer count toward the next one. A lockout that ended on its own does not reset the count,
     * as before.
     */
    public function failures(LoginAttemptKeys $keys, DateTimeImmutable $since): array
    {
        global $wpdb;

        [$pairs, $values] = $this->pairsClause($keys);
        $released = implode(', ', array_fill(0, count(LoginAttemptRecord::RELEASED), '%s'));
        // The inner pairs clause names the inner table's columns: unqualified, they resolve there first.
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE ' . $pairs . ' AND outcome = %s AND occurred_at >= %s'
            . ' AND occurred_at > COALESCE((SELECT MAX(locked_until) FROM ' . $this->table() . ' WHERE ' . $pairs
            . ' AND outcome = %s AND reason_code IN (' . $released . ')), %s)'
            . ' ORDER BY occurred_at ASC, id ASC',
            [
                ...$values,
                LoginAttemptRecord::FAILED,
                $this->date($since),
                ...$values,
                LoginAttemptRecord::LOCKED,
                ...LoginAttemptRecord::RELEASED,
                '1000-01-01 00:00:00',
            ],
        ), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    public function latestLockout(LoginAttemptKeys $keys, DateTimeImmutable $now): ?LoginAttemptRecord
    {
        global $wpdb;

        [$pairs, $values] = $this->pairsClause($keys);
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE ' . $pairs . ' AND outcome = %s AND locked_until > %s ORDER BY locked_until DESC, id DESC LIMIT 1',
            [...$values, LoginAttemptRecord::LOCKED, $this->date($now)],
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * Deletes up to `$limit` rows that occurred before `$cutoff`, oldest first, and returns how many.
     * A lockout still in force at `$now` is kept, however old.
     */
    public function pruneOlderThan(DateTimeImmutable $cutoff, DateTimeImmutable $now, int $limit = 500): int
    {
        global $wpdb;

        $limit = min(self::MAX_PRUNE_SIZE, max(1, $limit));
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . $this->table() . ' WHERE occurred_at < %s AND (locked_until IS NULL OR locked_until <= %s) ORDER BY occurred_at ASC, id ASC LIMIT %d',
            $this->date($cutoff),
            $this->date($now),
            $limit,
        ));
        $ids = array_values(array_filter(array_map('intval', is_array($ids) ? $ids : [])));

        if ($ids === []) {
            return 0;
        }

        $deleted = $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . $this->table() . ' WHERE id IN (' . implode(', ', array_fill(0, count($ids), '%d')) . ')',
            $ids,
        ));

        if ($deleted === false) {
            throw new RuntimeException('CoreX could not prune expired login attempts.');
        }

        return (int) $deleted;
    }

    /**
     * @return list<LoginAttemptRecord>
     */
    public function recentLockouts(DateTimeImmutable $now, int $limit = 20): array
    {
        global $wpdb;

        $limit = min(self::MAX_LOCKOUT_READ, max(1, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE outcome = %s ORDER BY locked_until DESC, id DESC LIMIT %d',
            LoginAttemptRecord::LOCKED,
            $limit,
        ), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    public function releaseActiveLockouts(DateTimeImmutable $now): int
    {
        return $this->release(LoginAttemptRecord::RELEASED_BY_RECOVERY, $now);
    }

    public function lockoutById(int $id): ?LoginAttemptRecord
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE id = %d AND outcome = %s',
            $id,
            LoginAttemptRecord::LOCKED,
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function releasePair(string $identityHash, string $networkHash, DateTimeImmutable $now): int
    {
        return $this->release(
            LoginAttemptRecord::RELEASED_BY_OWNER,
            $now,
            ' AND identity_hash = %s AND network_hash = %s',
            [$identityHash, $networkHash],
        );
    }

    public function releaseAllActive(DateTimeImmutable $now): int
    {
        return $this->release(LoginAttemptRecord::RELEASED_BY_OWNER, $now);
    }

    /**
     * Ends the lockouts still active at `$now` (and narrowed by `$where`), as released for `$reason`.
     * A row released already is never ended again, so its reason and its end stay the first ones.
     *
     * @param list<string> $values the placeholders' values of `$where`
     */
    private function release(string $reason, DateTimeImmutable $now, string $where = '', array $values = []): int
    {
        global $wpdb;

        $released = implode(', ', array_fill(0, count(LoginAttemptRecord::RELEASED), '%s'));
        $count = $wpdb->query($wpdb->prepare(
            'UPDATE ' . $this->table() . ' SET locked_until = %s, reason_code = %s'
            . ' WHERE outcome = %s AND locked_until > %s AND reason_code NOT IN (' . $released . ')' . $where,
            [
                $this->date($now),
                $reason,
                LoginAttemptRecord::LOCKED,
                $this->date($now),
                ...LoginAttemptRecord::RELEASED,
                ...$values,
            ],
        ));

        if ($count === false) {
            throw new RuntimeException('CoreX could not release active login lockouts.');
        }

        return (int) $count;
    }

    /**
     * The attempt's pairs as one condition: the keyed values, or the plain ones a row stored before
     * the update carries (spec 124, FR-019). Each pair is matched whole, through the
     * identity-and-network index.
     *
     * @return array{0:string,1:list<string>}
     */
    private function pairsClause(LoginAttemptKeys $keys): array
    {
        $conditions = [];
        $values = [];
        foreach ($keys->pairs() as [$identityHash, $networkHash]) {
            $conditions[] = '(identity_hash = %s AND network_hash = %s)';
            $values[] = $identityHash;
            $values[] = $networkHash;
        }

        return ['(' . implode(' OR ', $conditions) . ')', $values];
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): LoginAttemptRecord
    {
        return new LoginAttemptRecord(
            identityHash: (string) $row['identity_hash'],
            networkHash: (string) $row['network_hash'],
            outcome: (string) $row['outcome'],
            reasonCode: (string) $row['reason_code'],
            userId: isset($row['user_id']) ? (int) $row['user_id'] : null,
            occurredAt: $this->fromDate((string) $row['occurred_at']),
            retentionUntil: $this->fromDate((string) $row['retention_until']),
            lockedUntil: empty($row['locked_until']) ? null : $this->fromDate((string) $row['locked_until']),
            id: isset($row['id']) ? (int) $row['id'] : null,
        );
    }

    private function table(): string
    {
        return $this->migrator->fullName(LoginAttemptTable::NAME);
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
