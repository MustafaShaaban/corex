<?php

/**
 * Integration tests for the Notification Center REST boundary (spec 072 US1: FR-016..FR-018).
 *
 * Real WordPress, real tables, real REST dispatch. Proves the two-tier gate (read/own-action vs
 * manage), nonce enforcement on mutations, visibility filtering, and the envelope shape.
 *
 * **The actor is an account this file creates, not the install's administrator.** The suite runs
 * against a developer's real install, and these routes answer for whoever is signed in: an
 * administrator sees every notification the install holds, so "the list has one item" was only
 * true because each test began by deleting both tables — the developer's notifications and read
 * state with them. A fresh account with no role sees nothing but what a test addresses to it, by
 * id or through the one ability it is given, so the same assertions hold on a full table and
 * nothing of the developer's is read, changed or removed.
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
use Corex\Tests\Support\NotificationRows;

/** Every dedup key this file stores starts with this, which is how its rows are found again. */
const CONTROLLER_TEST_DEDUP = 'controller.test:';

/** An ability no producer targets and no role holds: only this file's actor sees through it. */
const CONTROLLER_TEST_ABILITY = 'corex_notification_controller_test';

const CONTROLLER_TEST_LOGIN = 'corex-notification-actor';

beforeEach(function () {
    $this->migrator = new Migrator();
    $this->migrator->create((new NotificationTable())->schema());
    $this->migrator->create((new NotificationUserStateTable())->schema());
    // A run that died before its afterEach leaves rows addressed to an account that is gone, and
    // a repeat of the same dedup key would merge into them and stay invisible to the new one.
    NotificationRows::forgetPrefixed(CONTROLLER_TEST_DEDUP);

    $this->repo = new WpNotificationRepository($this->migrator);
    (new NotificationController(new NotificationServiceImpl($this->repo), new WpNotificationPreferenceStore()))->register();

    // The same died-early run leaves the account too; take it over rather than fail to create it.
    $leftover = get_user_by('login', CONTROLLER_TEST_LOGIN);
    $this->actorId = $leftover instanceof WP_User ? (int) $leftover->ID : (int) wp_insert_user([
        'user_login' => CONTROLLER_TEST_LOGIN,
        'user_pass'  => wp_generate_password(20, true, true),
        'user_email' => CONTROLLER_TEST_LOGIN . '@example.com',
        // No role at all. A role is a door: the install may grant CoreX abilities to any of them.
        'role'       => '',
    ]);
    wp_set_current_user($this->actorId);
    wp_get_current_user()->add_cap(CONTROLLER_TEST_ABILITY);
});

afterEach(function () {
    NotificationRows::forgetPrefixed(CONTROLLER_TEST_DEDUP);

    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->actorId);
});

/** A notification the actor sees through {@see CONTROLLER_TEST_ABILITY}, unless told otherwise. */
function storeNotification(?NotificationRecipient $recipient = null, string $key = 'contact'): Notification
{
    return Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        severity: NotificationSeverity::ACTION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: CONTROLLER_TEST_DEDUP . $key,
        recipient: $recipient ?? NotificationRecipient::forAbility(CONTROLLER_TEST_ABILITY),
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
    $this->repo->upsertByDedupKey(storeNotification());

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
    $stored = $this->repo->upsertByDedupKey(storeNotification());

    // Logged in but no nonce → the own-action tier refuses (forbidden, not unauthorized).
    $denied = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/read');
    expect($denied->get_status())->toBe(403);

    $ok = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/read', true);
    expect($ok->get_status())->toBe(200)
        ->and(restCall('GET', '/corex/v1/notifications/count')->get_data()['data']['unread'])->toBe(0);
});

it('does not list or reveal a notification the actor may not see', function () {
    // Targeted at a different specific user — the actor is not that user and has no override here.
    $stored = $this->repo->upsertByDedupKey(
        storeNotification(NotificationRecipient::forUser($this->actorId + 999), 'other'),
    );

    expect(restCall('GET', '/corex/v1/notifications')->get_data()['data']['total'])->toBe(0)
        ->and(restCall('GET', '/corex/v1/notifications/' . $stored->id)->get_status())->toBe(404);
});

it('reads and saves per-category preferences, never muting a mandatory category', function () {
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
    wp_get_current_user()->add_cap(CorexAbility::MANAGE_NOTIFICATIONS);
    $stored = $this->repo->upsertByDedupKey(storeNotification());

    $response = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/resolve', true);
    expect($response->get_status())->toBe(200)
        ->and($this->repo->find($stored->id)->isResolved())->toBeTrue();
});

/**
 * The snooze route, which had no coverage at all and did not work.
 *
 * The screen posted `snoozed_until` — the name of the database *column* — while the route reads
 * `until`. `futureDate('')` returned null, so every snooze click answered 422 `invalid_snooze` and
 * the control looked alive while doing nothing (spec 087, FR-015).
 */
it('snoozes a notification on the parameter the client actually sends', function () {
    $stored = $this->repo->upsertByDedupKey(storeNotification());

    $request = new WP_REST_Request('POST', '/corex/v1/notifications/' . $stored->id . '/snooze');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $request->set_param('until', (new DateTimeImmutable('+1 day'))->format(DATE_ATOM));

    $response = rest_get_server()->dispatch($request);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['ok'])->toBeTrue();

    $listed = restCall('GET', '/corex/v1/notifications')->get_data()['data']['items'][0];
    expect($listed['user_state']['status'])->toBe('snoozed')
        ->and($listed['user_state']['snoozed_until'])->not->toBeNull();
});

it('refuses a snooze with no date rather than silently doing nothing', function () {
    $stored = $this->repo->upsertByDedupKey(storeNotification());

    $response = restCall('POST', '/corex/v1/notifications/' . $stored->id . '/snooze', true);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'])->toBe('invalid_snooze');
});

/**
 * The ability gate NotificationAction has documented since spec 072 and nothing enforced: the whole
 * action went out verbatim, so a viewer could be handed a link to a screen that would refuse them
 * on arrival (spec 087, FR-011 / FR-012).
 */
it('withholds an action the actor may not use, and does not file it under needs-action', function () {
    $note = Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        // INFORMATION, not ACTION: a demanding severity needs action on its own merits, so it would
        // mask the thing under test. With an undemanding one, the *only* reason this row could be
        // filed under "needs action" is the link — which is exactly what must not happen when the
        // link is withheld.
        severity: NotificationSeverity::INFORMATION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: CONTROLLER_TEST_DEDUP . 'gated',
        // Visible to the actor, so the *recipient* is not what is being tested here.
        recipient: NotificationRecipient::forAbility(CONTROLLER_TEST_ABILITY),
        occurredAt: new DateTimeImmutable('now'),
        action: \Corex\Notifications\NotificationAction::to(
            'notifications.submission.new.action',
            admin_url('admin.php?page=corex-submissions'),
            'corex_an_ability_nobody_holds',
            'Open the Submission Inbox',
        ),
    );
    $this->repo->upsertByDedupKey($note);

    $item = restCall('GET', '/corex/v1/notifications')->get_data()['data']['items'][0];

    expect($item)->not->toHaveKey('action')
        ->and($item['user_state']['needs_action'])->toBeFalse()
        ->and($item['user_state']['view'])->toBe('updates');
});

it('offers the action, with its label, to an actor who does hold the ability', function () {
    $note = Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        // The mirror of the test above, same undemanding severity: here the visible link is what
        // puts the row in "action needed", which is the behaviour the withheld case must not get.
        severity: NotificationSeverity::INFORMATION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: CONTROLLER_TEST_DEDUP . 'allowed',
        recipient: NotificationRecipient::forAbility(CONTROLLER_TEST_ABILITY),
        occurredAt: new DateTimeImmutable('now'),
        action: \Corex\Notifications\NotificationAction::to(
            'notifications.submission.new.action',
            admin_url('admin.php?page=corex-submissions'),
            CONTROLLER_TEST_ABILITY,
            'Open the Submission Inbox',
        ),
    );
    $this->repo->upsertByDedupKey($note);

    $item = restCall('GET', '/corex/v1/notifications')->get_data()['data']['items'][0];

    // The label the author wrote, all the way through storage to the wire — this is the trip that
    // used to lose it, leaving the client with a translation key it had no way to resolve.
    expect($item['action']['label'])->toBe('Open the Submission Inbox')
        ->and($item['action']['url'])->toContain('page=corex-submissions')
        ->and($item['user_state']['needs_action'])->toBeTrue();
});
