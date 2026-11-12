<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Lockouts;

defined('ABSPATH') || exit;

use Corex\Config\Notifications\NotificationTable;
use Corex\Database\Schema\Migrator;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * The notifications table, read and written for the rewrite of older lockout notices.
 *
 * A notice the release before stored has the source type `login_identity` (or none); one written to
 * the new rule has `login_account` or `login_fingerprint`. The read selects by that, so a rewritten
 * notice is never read again. The table name is resolved on every call, so in a run that switches
 * between a network's sites each call reaches the site in scope.
 */
final class WpLockoutNoticeStore implements LockoutNoticeStore
{
    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function legacyAfter(int $afterId, int $limit): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT id, source_type, source_id, dedup_key, metadata_json, latest_occurred_at FROM ' . $this->table()
            . ' WHERE type = %s AND id > %d AND (source_type IS NULL OR source_type NOT IN (%s, %s)) ORDER BY id ASC LIMIT %d',
            LoginLockoutNotice::TYPE,
            $afterId,
            LoginLockoutNotice::SOURCE_ACCOUNT,
            LoginLockoutNotice::SOURCE_FINGERPRINT,
            max(1, $limit),
        ), ARRAY_A);

        // A failed query still answers an empty array, which would read as "nothing left" and let
        // the schema version be stored with old notices unrewritten; the error is what tells them apart.
        if (! is_array($rows) || $wpdb->last_error !== '') {
            throw new RuntimeException('CoreX could not read the lockout notices to rewrite.');
        }

        return array_map($this->hydrate(...), $rows);
    }

    public function rewrite(int $id, LoginLockoutNotice $notice): void
    {
        global $wpdb;

        $updated = $wpdb->update($this->table(), [
            'rendered_json' => $this->json($notice->rendered),
            'dedup_key'     => $notice->dedupKey,
            'source_type'   => $notice->sourceType,
            'source_id'     => $notice->sourceId,
            'metadata_json' => $this->json($notice->metadata),
        ], ['id' => $id]);

        if ($updated === false) {
            throw new RuntimeException('CoreX could not rewrite a lockout notice.');
        }
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): StoredLockoutNotice
    {
        $metadata = json_decode((string) $row['metadata_json'], true);

        return new StoredLockoutNotice(
            (int) $row['id'],
            $row['source_type'] === null ? null : (string) $row['source_type'],
            $row['source_id'] === null ? null : (string) $row['source_id'],
            (string) $row['dedup_key'],
            is_array($metadata) ? $metadata : [],
            new DateTimeImmutable((string) $row['latest_occurred_at'], new DateTimeZone('UTC')),
        );
    }

    private function table(): string
    {
        return $this->migrator->fullName(NotificationTable::NAME);
    }

    /** @param array<string,mixed> $value */
    private function json(array $value): string
    {
        $encoded = wp_json_encode($value);
        if (! is_string($encoded)) {
            throw new RuntimeException('CoreX could not encode a lockout notice.');
        }

        return $encoded;
    }
}
