<?php

/**
 * Performance guard for the notification store at scale (spec 072: FR-026, SC — bounded reads).
 *
 * Seeds 10k notifications, then proves the reads the UI depends on stay bounded: the unread count, a
 * filtered page, and a prune each complete well within budget rather than scanning the whole table.
 * The store caps its candidate scan (500 rows) and paginates in PHP, so growth does not slow reads.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Notifications\WpNotificationRepository;
use Corex\Database\Schema\Migrator;
use Corex\Notifications\NotificationQuery;

beforeEach(function () {
    global $wpdb;
    $this->migrator = new Migrator();
    $this->migrator->create((new NotificationTable())->schema());
    $this->migrator->create((new NotificationUserStateTable())->schema());
    $this->table = $this->migrator->fullName(NotificationTable::NAME);
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(NotificationUserStateTable::NAME));
    $wpdb->query('DELETE FROM ' . $this->table);
    $this->repo = new WpNotificationRepository($this->migrator);
    $this->allow = static fn (string $ability): bool => true;
});

afterEach(function () {
    global $wpdb;
    $wpdb->query('DELETE FROM ' . $this->table); // never leave the 10k seed rows behind
});

it('keeps unread count, a filtered page, and prune bounded across 10k notifications', function () {
    global $wpdb;

    // Bulk-seed 10,000 ability-targeted notifications directly (fast path — the store's own writes
    // are exercised elsewhere; here we only measure reads at scale).
    $now = gmdate('Y-m-d H:i:s');
    $recipient = wp_json_encode(['kind' => 'ability', 'ability' => 'corex_manage_submissions']);
    $rendered  = wp_json_encode(['title' => 'Scale', 'body' => 'Scale']);
    $values = [];
    for ($i = 0; $i < 10000; $i++) {
        $uuid = sprintf('%08x-0000-4000-8000-%012x', $i, $i);
        $values[] = $wpdb->prepare(
            '(%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s)',
            $uuid, 'submission.new', 'submissions', 'action', 'forms', 'k.title', 'k.body',
            $rendered, 'perf:' . $i, 1, $now, $now, $now, $now, $recipient, wp_json_encode([]),
        );
    }
    // Insert in chunks to stay within packet limits.
    foreach (array_chunk($values, 1000) as $chunk) {
        $wpdb->query(
            'INSERT INTO ' . $this->table
            . ' (uuid,type,category,severity,source_module,title_key,message_key,rendered_json,dedup_key,'
            . 'occurrences,first_occurred_at,latest_occurred_at,created_at,updated_at,recipient_json,metadata_json) '
            . 'VALUES ' . implode(',', $chunk)
        );
    }
    expect((int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->table))->toBe(10000);

    $budget = 2.0; // seconds — generous; a bounded read is orders of magnitude faster.

    $t = microtime(true);
    $count = $this->repo->unreadCountForActor(7, $this->allow);
    expect(microtime(true) - $t)->toBeLessThan($budget)->and($count)->toBeGreaterThan(0);

    $t = microtime(true);
    $page = $this->repo->queryForActor(NotificationQuery::fromRequest([], 1, 20), 7, $this->allow);
    expect(microtime(true) - $t)->toBeLessThan($budget)
        ->and(count($page['items']))->toBeLessThanOrEqual(20);

    $t = microtime(true);
    $this->repo->pruneOlderThan(new DateTimeImmutable('2000-01-01'), 500);
    expect(microtime(true) - $t)->toBeLessThan($budget);
});
