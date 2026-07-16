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
 * WordPress persistence adapter for hashed login attempt and lockout evidence.
 */
final class WpLoginAttemptStore implements LoginAttemptStore, LoginLockoutStore, LoginLockoutReader
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

    public function failures(string $identityHash, string $networkHash, DateTimeImmutable $since): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE identity_hash = %s AND network_hash = %s AND outcome = %s AND occurred_at >= %s ORDER BY occurred_at ASC, id ASC',
            $identityHash,
            $networkHash,
            LoginAttemptRecord::FAILED,
            $this->date($since),
        ), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    public function latestLockout(string $identityHash, string $networkHash, DateTimeImmutable $now): ?LoginAttemptRecord
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE identity_hash = %s AND network_hash = %s AND outcome = %s AND locked_until > %s ORDER BY locked_until DESC, id DESC LIMIT 1',
            $identityHash,
            $networkHash,
            LoginAttemptRecord::LOCKED,
            $this->date($now),
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
    {
        global $wpdb;

        $limit = min(self::MAX_PRUNE_SIZE, max(1, $limit));
        $ids = $wpdb->get_col($wpdb->prepare(
            'SELECT id FROM ' . $this->table() . ' WHERE retention_until <= %s ORDER BY retention_until ASC, id ASC LIMIT %d',
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
        global $wpdb;

        $released = $wpdb->query($wpdb->prepare(
            'UPDATE ' . $this->table() . ' SET locked_until = %s, reason_code = %s WHERE outcome = %s AND locked_until > %s',
            $this->date($now),
            'released_by_recovery',
            LoginAttemptRecord::LOCKED,
            $this->date($now),
        ));

        if ($released === false) {
            throw new RuntimeException('CoreX could not release active login lockouts.');
        }

        return (int) $released;
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
