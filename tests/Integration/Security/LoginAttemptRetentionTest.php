<?php

/**
 * Sign-in records are deleted once they are older than the retention period (spec 124, FR-019,
 * DECISIONS #626), on real WordPress.
 *
 * `retain_days` was saved and shown, and nothing deleted a row: the store's prune had no caller.
 * A row stored before the update holds a plain SHA-256 of the username and the address, which can be
 * read back by guessing, so it has to go. The attempts table is now one of the stores the daily
 * retention sweep prunes, with the window the login settings give.
 *
 * The rows these tests write are dated in 2001, so a prune they run reaches no real row of the site.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Retention\RetentionSweep;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginAttemptRetention;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;
use Corex\Database\Schema\Migrator;

/** The address these rows are recorded against, in a range reserved for documentation. */
const RETENTION_TEST_NETWORK = '198.51.100.91';

function retentionTestRow(string $occurredAt, ?string $lockedUntil = null): void
{
    $at = new DateTimeImmutable($occurredAt);
    Boot::app()->container()->make(WpLoginAttemptStore::class)->record(new LoginAttemptRecord(
        identityHash: hash('sha256', 'retention-' . $occurredAt),
        networkHash: hash('sha256', RETENTION_TEST_NETWORK),
        outcome: $lockedUntil === null ? LoginAttemptRecord::FAILED : LoginAttemptRecord::LOCKED,
        reasonCode: 'invalid_credentials',
        userId: null,
        occurredAt: $at,
        retentionUntil: $at->modify('+30 days'),
        lockedUntil: $lockedUntil === null ? null : new DateTimeImmutable($lockedUntil),
    ));
}

/** @return list<string> when each of these tests' rows that are left occurred */
function retentionTestRowsLeft(): array
{
    global $wpdb;

    return array_map('strval', $wpdb->get_col($wpdb->prepare(
        'SELECT occurred_at FROM ' . (new Migrator())->fullName(LoginAttemptTable::NAME) . ' WHERE network_hash = %s ORDER BY occurred_at ASC',
        hash('sha256', RETENTION_TEST_NETWORK),
    )));
}

afterEach(function () {
    global $wpdb;

    $wpdb->delete((new Migrator())->fullName(LoginAttemptTable::NAME), ['network_hash' => hash('sha256', RETENTION_TEST_NETWORK)]);
});

it('deletes a sign-in record past its retention and keeps one inside it', function () {
    retentionTestRow('2001-01-01 10:00:00');
    retentionTestRow('2001-01-20 10:00:00');
    retentionTestRow('2001-03-01 10:00:00');

    $removed = Boot::app()->container()->make(LoginAttemptRetention::class)
        ->pruneOlderThan(new DateTimeImmutable('2001-02-01 00:00:00', new DateTimeZone('UTC')));

    expect($removed)->toBe(2)
        ->and(retentionTestRowsLeft())->toBe(['2001-03-01 10:00:00']);
});

it('keeps a lockout that has not ended, however old it is', function () {
    retentionTestRow('2001-01-01 10:00:00', '2099-01-01 00:00:00');

    Boot::app()->container()->make(LoginAttemptRetention::class)
        ->pruneOlderThan(new DateTimeImmutable('2001-02-01 00:00:00', new DateTimeZone('UTC')));

    expect(retentionTestRowsLeft())->toBe(['2001-01-01 10:00:00']);
});

it('deletes in batches until none is left', function () {
    foreach (range(1, 7) as $day) {
        retentionTestRow(sprintf('2001-01-%02d 10:00:00', $day));
    }

    $retention = new LoginAttemptRetention(
        Boot::app()->container()->make(WpLoginAttemptStore::class),
        Boot::app()->container()->make(\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore::class),
        batchSize: 3,
    );
    $retention->pruneOlderThan(new DateTimeImmutable('2001-02-01 00:00:00', new DateTimeZone('UTC')));

    expect(retentionTestRowsLeft())->toBe([]);
});

it('is one of the stores the daily retention sweep prunes, with the window the login settings give', function () {
    $plan = Boot::app()->container()->make(RetentionSweep::class)->preview();
    $entry = array_values(array_filter($plan, static fn (array $store): bool => $store['key'] === 'login_attempts'));
    $days = Boot::app()->container()->make(\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore::class)->current()->retainDays;

    expect($entry)->toHaveCount(1)
        ->and($entry[0]['retentionDays'])->toBe($days)
        ->and($entry[0]['enabled'])->toBeTrue();
});
