<?php

/**
 * Integration tests for the Notification Center REST boundary (spec 072 US1: FR-016..FR-018).
 *
 * Real WordPress, real tables, real REST dispatch. Proves the two-tier gate (read/own-action vs
 * manage), nonce enforcement on mutations, visibility filtering, and the envelope shape.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Config\Notifications\NotificationController;
use Corex\Config\Notifications\NotificationServiceImpl;
use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Notifications\WpNotificationPreferenceStore;
use Corex\Config\Notifications\WpNotificationRepository;
use Corex\Database\Schema\Migrator;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationSeverity;

beforeEach(function () {
    global $wpdb;
    $this->migrator = new Migrator();
    $this->migrator->create((new NotificationTable())->schema());
    $this->migrator->create((new NotificationUserStateTable())->schema());
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(NotificationUserStateTable::NAME));
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(NotificationTable::NAME));

    $this->repo = new WpNotificationRepository($this->migrator);
    (new NotificationController(new NotificationServiceImpl($this->repo), new WpNotificationPreferenceStore()))->register();

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->adminId = (int) ($admins[0] ?? 0);
    wp_set_current_user($this->adminId);
});

function storeNotification(NotificationRecipient $recipient, string $dedup = 'submission.new:contact'): Notification
{
    return Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        severity: NotificationSeverity::ACTION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: $dedup,
        recipient: $recipient,
        occurredAt: new DateTimeImmutable('now'),
    );
}

function restCall(string $method, string $route, bool $withNonce = false): WP_REST_Response
{
    $request = new WP_REST_Request($method, $route);
    if ($withNonce) {
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    }

    return rest_get_server()->dispatch($request);
}

it('lists the actor’s notifications and counts the unread ones', function () {
    $this->repo->upsertByDedupKey(storeNotification(NotificationRecipient::forAbility(CorexAbility::MANAGE_SUBMISSIONS)));

    $list = restCall('GET', '/corex/v1/notifications');
    expect($list->get_status())->toBe(200);
    $data = $list->get_data();
    expect($data['ok'])->toBeTrue()
        ->and($data['data']['total'])->toBe(1)
        ->and($data['data']['items'][0]['type'])->toBe('submission.new');

    $count = restCall('GET', '/corex/v1/notifications/count');
    expect($count->get_data()['data']['unread'])->toBe(1);
});

it('marks a notification read only with a nonce, and refuses without one', function () {
    $stored = $this->repo->upsertByDedupKey(storeNotification(NotificationRecipient::forAbility(CorexAbility::MANAGE_SUBMISSIONS)));

    // Logged in but no nonce → the own-action tier refuses (forbidden, not unauthorized).
    $denied = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/read');
    expect($denied->get_status())->toBe(403);

    $ok = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/read', true);
    expect($ok->get_status())->toBe(200)
        ->and(restCall('GET', '/corex/v1/notifications/count')->get_data()['data']['unread'])->toBe(0);
});

it('does not list or reveal a notification the actor may not see', function () {
    // Targeted at a different specific user — the admin is not that user and lacks any override here.
    $stored = $this->repo->upsertByDedupKey(
        storeNotification(NotificationRecipient::forUser($this->adminId + 999), 'submission.new:other'),
    );

    expect(restCall('GET', '/corex/v1/notifications')->get_data()['data']['total'])->toBe(0)
        ->and(restCall('GET', '/corex/v1/notifications/' . $stored->id)->get_status())->toBe(404);
});

it('reads and saves per-category preferences, never muting a mandatory category', function () {
    delete_user_meta($this->adminId, 'corex_notification_preferences');

    $initial = restCall('GET', '/corex/v1/notifications/preferences')->get_data();
    expect($initial['ok'])->toBeTrue();
    $security = array_values(array_filter(
        $initial['data']['preferences'],
        static fn (array $row): bool => $row['category'] === 'security',
    ))[0];
    expect($security['mandatory'])->toBeTrue()->and($security['enabled'])->toBeTrue();

    // Try to mute jobs (allowed) and security (mandatory — must stay on).
    $request = new WP_REST_Request('POST', '/corex/v1/notifications/preferences');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_param('categories', ['jobs' => false, 'security' => false]);
    $saved = rest_get_server()->dispatch($request)->get_data()['data']['preferences'];

    $byCategory = [];
    foreach ($saved as $row) {
        $byCategory[$row['category']] = $row['enabled'];
    }
    expect($byCategory['jobs'])->toBeFalse()        // user muted it
        ->and($byCategory['security'])->toBeTrue(); // mandatory — never muted
});

it('resolves a condition through the manage tier', function () {
    $stored = $this->repo->upsertByDedupKey(storeNotification(NotificationRecipient::forAbility(CorexAbility::MANAGE_SUBMISSIONS)));

    $response = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/resolve', true);
    expect($response->get_status())->toBe(200)
        ->and($this->repo->find($stored->id)->isResolved())->toBeTrue();
});
