<?php

/**
 * What the account block's two lists are handed, asked through the real REST server.
 *
 * A row of recent activity was an event's internal name and an ISO 8601 date in UTC
 * (`profile.updated · 2026-10-13T13:23:23+00:00`), and an administrator was also listed what
 * they did in the admin. A session was its whole user agent string. Measured on 2026-10-13
 * (spec 068, FR-161g): the routes now hand over a sentence, a device name and dates as the
 * site writes them, and the script prints them.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Profile\Notification\AccountActivity;

const LIST_ROWS_SITE_SETTINGS = [
    // Three hours ahead of UTC all year: half past nine in the evening there is the next day here.
    'timezone_string' => 'Asia/Riyadh',
    'date_format'     => 'j F Y',
    'time_format'     => 'H:i',
];

const LIST_ROWS_FIREFOX_ON_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:143.0) Gecko/20100101 Firefox/143.0';

/**
 * Records an event with the signed-in person as its actor, as the product's own code does.
 */
function recordForListRows(string $kind, string $area, string $occurredAt): void
{
    $userId = get_current_user_id();

    Boot::app()->container()->make(ActivityService::class)->record(
        actorId: $userId,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'List rows',
        area: $area,
        kind: $kind,
        targetType: 'user',
        targetId: (string) $userId,
        targetLabel: 'List rows',
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => $kind, 'args' => []],
        context: [],
        sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
        retentionUntil: new DateTimeImmutable('+1 day'),
        occurredAt: new DateTimeImmutable($occurredAt),
    );
}

/**
 * @return list<array<string, mixed>>
 */
function askForListRows(string $list): array
{
    $routes   = ['notifications' => '/corex/v1/account/notifications', 'sessions' => '/corex/v1/account/sessions'];
    $response = rest_do_request(new WP_REST_Request('GET', $routes[$list]));

    return $response->get_data()[$list];
}

beforeEach(function () {
    $this->settings = [];
    foreach (LIST_ROWS_SITE_SETTINGS as $name => $value) {
        $this->settings[$name] = get_option($name);
        update_option($name, $value);
    }

    $tag          = strtolower(wp_generate_password(8, false, false));
    $this->userId = (int) wp_insert_user([
        'user_login' => 'rows_' . $tag,
        'user_email' => 'rows.' . $tag . '@example.com',
        'user_pass'  => wp_generate_password(24),
        'role'       => 'subscriber',
    ]);
    wp_set_current_user($this->userId);
});

afterEach(function () {
    global $wpdb;

    unset($_SERVER['HTTP_USER_AGENT']);
    wp_set_current_user(0);
    WP_Session_Tokens::get_instance($this->userId)->destroy_all();
    $wpdb->delete($wpdb->prefix . 'corex_activity_events', ['actor_id' => $this->userId]);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->userId);

    foreach ($this->settings as $name => $value) {
        update_option($name, $value);
    }
});

it('lists a profile saved through the block as a sentence', function () {
    $save = new WP_REST_Request('POST', '/corex/v1/account/profile');
    $save->set_body_params(['display_name' => 'Rows Renamed']);
    rest_do_request($save);

    $rows = askForListRows('notifications');

    expect(array_column($rows, 'label'))->toBe(['Your profile was updated.']);
});

it('says when it happened in the site\'s own timezone and date format', function () {
    recordForListRows(AccountActivity::PROFILE_UPDATED, ActivityEvent::AREA_SECURITY, '2026-10-13T21:30:00+00:00');

    $row = askForListRows('notifications')[0];

    expect($row['when'])->toBe('14 October 2026 at 00:30')
        // The instant itself is still there for a program, as it was.
        ->and($row['occurredAt'])->toBe('2026-10-13T21:30:00+00:00');
});

it('leaves out what the person did elsewhere in the product, however much of it came later', function () {
    recordForListRows(AccountActivity::PROFILE_UPDATED, ActivityEvent::AREA_SECURITY, '2026-10-01T09:00:00+00:00');
    foreach (range(1, 12) as $minute) {
        recordForListRows('submission.export.queued', ActivityEvent::AREA_SUBMISSIONS, sprintf('2026-10-02T09:%02d:00+00:00', $minute));
    }

    $rows = askForListRows('notifications');

    expect(array_column($rows, 'kind'))->toBe([AccountActivity::PROFILE_UPDATED]);
});

it('lists the newest ten of what it has a sentence for, newest first, whatever their kind', function () {
    foreach (range(1, 6) as $hour) {
        recordForListRows(AccountActivity::PROFILE_UPDATED, ActivityEvent::AREA_SECURITY, sprintf('2026-10-05T%02d:00:00+00:00', $hour));
        recordForListRows(AccountActivity::OTHER_SESSIONS_SIGNED_OUT, ActivityEvent::AREA_SECURITY, sprintf('2026-10-05T%02d:30:00+00:00', $hour));
    }

    $rows = askForListRows('notifications');

    expect(array_column($rows, 'when'))->toBe([
        '5 October 2026 at 09:30',
        '5 October 2026 at 09:00',
        '5 October 2026 at 08:30',
        '5 October 2026 at 08:00',
        '5 October 2026 at 07:30',
        '5 October 2026 at 07:00',
        '5 October 2026 at 06:30',
        '5 October 2026 at 06:00',
        '5 October 2026 at 05:30',
        '5 October 2026 at 05:00',
    ]);
});

it('names a session by its browser and system, and says when it signed in', function () {
    $_SERVER['HTTP_USER_AGENT'] = LIST_ROWS_FIREFOX_ON_LINUX;
    $sessions = WP_Session_Tokens::get_instance($this->userId);
    $token    = $sessions->create(time() + DAY_IN_SECONDS);
    $sessions->update($token, ['login' => (new DateTimeImmutable('2026-10-13T21:30:00+00:00'))->getTimestamp()] + $sessions->get($token));

    $row = askForListRows('sessions')[0];

    expect($row['device'])->toBe('Firefox on Linux')
        ->and($row['signedIn'])->toBe('Signed in 14 October 2026 at 00:30')
        // What a program read before is still there.
        ->and($row['ua'])->toBe(LIST_ROWS_FIREFOX_ON_LINUX);
});
