<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Access\AccessRequestStore;
use Corex\Database\Schema\Migrator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Persists terminal, append-history access request decisions.
 */
final class AccessRequestRepository implements AccessRequestStore
{
    private const STATES = ['approved', 'denied', 'cancelled', 'expired'];

    /** How many open requests one read returns. See {@see pending()}. */
    private const PENDING_LIMIT = 100;

    /**
     * How long a second ask waits for the first to finish before it gives up.
     *
     * What it waits for is one read and one insert, so this is only ever reached by a database
     * that has stopped answering.
     */
    private const LOCK_WAIT_SECONDS = 5;

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function createUnlessPending(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): ?int {
        global $wpdb;

        // One requester's asks go one at a time. The table cannot refuse the second row itself:
        // a person may have any number of decided requests for one ability and only one open, and
        // a unique index cannot say "only among the open ones". A named lock is held by this
        // connection and let go by MySQL if the request dies, so nothing is left to clear.
        $lock = $this->lockName($requesterId);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock, self::LOCK_WAIT_SECONDS)) !== 1) {
            throw new RuntimeException('CoreX could not create the access request.');
        }

        try {
            if ($this->pendingFor($requesterId, $abilityKey, $areaKey) !== null) {
                return null;
            }

            return $this->insert($requesterId, $abilityKey, $areaKey, $reason, $createdAt, $expiresAt);
        } finally {
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private function insert(
        int $requesterId,
        ?string $abilityKey,
        ?string $areaKey,
        string $reason,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $expiresAt,
    ): int {
        global $wpdb;

        $inserted = $wpdb->insert($this->table(), [
            'requester_id'            => $requesterId,
            'ability_key'             => $abilityKey,
            'area_key'                => $areaKey,
            'reason'                  => $reason,
            'state'                   => 'pending',
            'reviewer_id'             => null,
            'review_note'             => null,
            'reviewed_at'             => null,
            'notification_attempt_id' => null,
            'created_at'              => $this->date($createdAt),
            'expires_at'              => $this->date($expiresAt),
        ]);

        if ($inserted === false || (int) $wpdb->insert_id < 1) {
            throw new RuntimeException('CoreX could not create the access request.');
        }

        return (int) $wpdb->insert_id;
    }

    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE id = %d',
            $id,
        ), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function transition(
        int $id,
        string $state,
        int $reviewerId,
        string $note,
        DateTimeImmutable $reviewedAt,
    ): bool {
        global $wpdb;

        if (! in_array($state, self::STATES, true)) {
            return false;
        }

        $updated = $wpdb->query($wpdb->prepare(
            'UPDATE ' . $this->table()
            . ' SET state = %s, reviewer_id = %d, review_note = %s, reviewed_at = %s'
            . ' WHERE id = %d AND state = %s',
            $state,
            $reviewerId,
            $note,
            $this->date($reviewedAt),
            $id,
            'pending',
        ));

        return $updated === 1;
    }

    public function pending(): array
    {
        global $wpdb;

        // Capped, because spec 079 put this list on a rendered page. Open requests are naturally
        // few — one per person per ability, expiring after seven days — but "naturally few" is a
        // property of how people behave, not of the query, and this one has no other bound. A site
        // with more waiting than fit here has a queue problem the page cannot solve by being longer.
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $this->table()
            . ' WHERE state = %s AND expires_at > %s ORDER BY created_at ASC, id ASC LIMIT %d',
            'pending',
            gmdate('Y-m-d H:i:s'),
            self::PENDING_LIMIT,
        ), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
    }

    public function pendingFor(int $requesterId, ?string $abilityKey, ?string $areaKey): ?array
    {
        global $wpdb;

        // The unused half of the pair is NULL in the table, and NULL is not matched by `= %s`:
        // prepare() casts a null argument to the empty string, so a placeholder would compare
        // against '' and never match. Each side is therefore either a literal `IS NULL` or a real
        // placeholder — never a placeholder holding null. Both fragments are constants; only the
        // values are bound.
        $sql  = 'SELECT * FROM ' . $this->table() . ' WHERE requester_id = %d';
        $args = [$requesterId];

        foreach (['ability_key' => $abilityKey, 'area_key' => $areaKey] as $column => $value) {
            if ($value === null) {
                $sql .= ' AND ' . $column . ' IS NULL';

                continue;
            }

            $sql   .= ' AND ' . $column . ' = %s';
            $args[] = $value;
        }

        $sql   .= ' AND state = %s AND expires_at > %s ORDER BY created_at DESC, id DESC LIMIT 1';
        $args[] = 'pending';
        $args[] = gmdate('Y-m-d H:i:s');

        $row = $wpdb->get_row($wpdb->prepare($sql, $args), ARRAY_A);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function hydrate(array $row): array
    {
        return [
            'id'                    => (int) $row['id'],
            'requesterId'           => (int) $row['requester_id'],
            'abilityKey'            => $row['ability_key'] !== null ? (string) $row['ability_key'] : null,
            'areaKey'               => $row['area_key'] !== null ? (string) $row['area_key'] : null,
            'reason'                => (string) $row['reason'],
            'state'                 => (string) $row['state'],
            'reviewerId'            => $row['reviewer_id'] !== null ? (int) $row['reviewer_id'] : null,
            'reviewNote'            => $row['review_note'] !== null ? (string) $row['review_note'] : null,
            'reviewedAt'            => $this->nullableDate($row['reviewed_at']),
            'notificationAttemptId' => $row['notification_attempt_id'] !== null ? (int) $row['notification_attempt_id'] : null,
            'createdAt'             => $this->storedDate((string) $row['created_at']),
            'expiresAt'             => $this->storedDate((string) $row['expires_at']),
        ];
    }

    private function table(): string
    {
        return $this->migrator->fullName(AccessTables::REQUESTS);
    }

    /**
     * A name no other site on the same database server arrives at.
     *
     * MySQL's named locks are shared by every database on the server, so the name carries the
     * database and the table, which carries the site's prefix. Hashed because a name may be no
     * longer than 64 characters.
     */
    private function lockName(int $requesterId): string
    {
        global $wpdb;

        return 'corex_access_' . md5($wpdb->dbname . '|' . $this->table() . '|' . $requesterId);
    }

    private function date(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function storedDate(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('UTC'));
    }

    private function nullableDate(mixed $date): ?DateTimeImmutable
    {
        return is_string($date) && $date !== '' ? $this->storedDate($date) : null;
    }
}
