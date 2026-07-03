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

    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function create(
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

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . $this->table() . ' WHERE state = %s AND expires_at > %s ORDER BY created_at ASC, id ASC',
            'pending',
            gmdate('Y-m-d H:i:s'),
        ), ARRAY_A);

        return array_map($this->hydrate(...), is_array($rows) ? $rows : []);
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
