<?php

/**
 * A display name of any length, at the account add-on's Profile route.
 *
 * Measured over HTTP on WordPress 7.1.3 on 2026-10-13 (DECISIONS #340). WordPress holds a
 * display name in 250 characters and the activity record held a label in 191:
 *
 * - a name of 192 to 250 characters was saved, and the request then answered 500, because
 *   the record of the change was refused by its table;
 * - a name of 251 was not stored, nor was anything else of the account's row, and nothing
 *   said so; a first name sent with it was;
 * - 250 characters of which ten were `&` answered "Your profile has been saved." and kept the
 *   old name, because WordPress holds each `&` as five;
 * - WordPress's own profile screen takes a nickname of 250 characters as the display name,
 *   and from then on every save at the route answered 500, and so did "sign out other
 *   sessions".
 *
 * On real WordPress, through the route as `rest_do_request()` dispatches it: an exception the
 * route does not catch fails the test, as it failed the request.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Boot;
use Corex\Config\Activity\ActivityTable;
use Corex\Database\Schema\Migrator;
use Corex\Profile\Account\AccountService;
use Corex\Profile\Account\AuthGateway;

function displayNameOfLength(int $length, string $letter = 'n'): string
{
    return str_repeat($letter, $length);
}

/** @param array<string,string> $fields */
function postToProfileRoute(array $fields): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/account/profile');
    $request->set_body_params($fields);

    return rest_do_request($request);
}

function displayNameInTheDatabase(int $userId): string
{
    global $wpdb;

    return (string) $wpdb->get_var($wpdb->prepare("SELECT display_name FROM {$wpdb->users} WHERE ID = %d", $userId));
}

/** @return list<string> The actor's label of each record written about this account, oldest first. */
function labelsRecordedFor(int $userId): array
{
    global $wpdb;
    $table = Boot::app()->container()->make(Migrator::class)->fullName(ActivityTable::NAME);

    return $wpdb->get_col($wpdb->prepare(
        "SELECT actor_label FROM {$table} WHERE target_type = %s AND target_id = %s ORDER BY id ASC",
        'user',
        (string) $userId,
    ));
}

beforeEach(function () {
    // Nothing here changes an address, and no test of this suite may send mail whatever it does.
    add_filter('pre_wp_mail', '__return_true');
    require_once ABSPATH . 'wp-admin/includes/user.php';

    $login = 'named' . strtolower(wp_generate_password(10, false));
    $this->userId = (int) wp_insert_user([
        'user_login' => $login,
        'user_pass' => wp_generate_password(24),
        'user_email' => $login . '@example.com',
        'display_name' => 'Mona Before',
        'first_name' => 'Mona',
    ]);
    wp_set_current_user($this->userId);
});

afterEach(function () {
    global $wpdb;

    $wpdb->delete(
        Boot::app()->container()->make(Migrator::class)->fullName(ActivityTable::NAME),
        ['target_type' => 'user', 'target_id' => (string) $this->userId],
    );
    wp_set_current_user(0);
    wp_delete_user($this->userId);
    remove_filter('pre_wp_mail', '__return_true');
});

it('saves a display name longer than the activity record holds, and records the change', function (string $name) {
    $response = postToProfileRoute(['display_name' => $name]);

    $labels = labelsRecordedFor($this->userId);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['code'])->toBe('updated')
        ->and(displayNameInTheDatabase($this->userId))->toBe($name)
        ->and($labels)->toHaveCount(1)
        ->and($labels[0])->toBe(mb_substr($name, 0, ActivityEvent::LONGEST_TEXT - 1) . '…');
})->with([
    'one character more than the record holds' => [displayNameOfLength(ActivityEvent::LONGEST_TEXT + 1)],
    'the longest WordPress keeps' => [displayNameOfLength(AccountService::MAX_DISPLAY_NAME_LENGTH)],
    'the longest WordPress keeps, in Arabic' => [displayNameOfLength(AccountService::MAX_DISPLAY_NAME_LENGTH, 'م')],
]);

it('refuses a display name WordPress would not keep, and saves nothing that was sent with it', function (string $name) {
    $response = postToProfileRoute(['display_name' => $name, 'first_name' => 'Changed']);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'])->toBe('display_name_too_long')
        ->and(displayNameInTheDatabase($this->userId))->toBe('Mona Before')
        ->and(get_user_meta($this->userId, 'first_name', true))->toBe('Mona')
        ->and(labelsRecordedFor($this->userId))->toBe([]);
})->with([
    'one character more than WordPress keeps' => [displayNameOfLength(AccountService::MAX_DISPLAY_NAME_LENGTH + 1)],
    'one more, in Arabic' => [displayNameOfLength(AccountService::MAX_DISPLAY_NAME_LENGTH + 1, 'م')],
    'as many as WordPress keeps, of which ten are held as five each' => [
        str_repeat('&', 10) . displayNameOfLength(AccountService::MAX_DISPLAY_NAME_LENGTH - 10),
    ],
]);

it('keeps working for an account whose display name was made long on a WordPress screen', function () {
    // What `wp-admin/profile.php` does with a nickname of 250 characters chosen as the name.
    $name = displayNameOfLength(AccountService::MAX_DISPLAY_NAME_LENGTH);
    wp_update_user(['ID' => $this->userId, 'nickname' => $name, 'display_name' => $name]);

    $profile  = postToProfileRoute(['first_name' => 'Changed']);
    $sessions = rest_do_request(new WP_REST_Request('POST', '/corex/v1/account/sessions/revoke-others'));

    expect($profile->get_status())->toBe(200)
        ->and(get_user_meta($this->userId, 'first_name', true))->toBe('Changed')
        ->and($sessions->get_status())->toBe(200)
        ->and(labelsRecordedFor($this->userId))->toHaveCount(2);
});

it('measures a display name as WordPress stores it', function (string $typed) {
    $gateway = Boot::app()->container()->make(AuthGateway::class);
    $kept    = $gateway->displayNameAsKept($typed);

    $gateway->updateProfile($this->userId, ['display_name' => $typed]);

    expect(displayNameInTheDatabase($this->userId))->toBe($kept);
})->with([
    'plain' => ['Mona Hassan'],
    'an ampersand' => ['Tom & Jerry'],
    'a less-than sign and quotes after it' => ['a <3 "b"'],
    'markup' => ['<b>Mona</b> Hassan'],
    'spaces around it' => ['  Mona   Hassan  '],
    'a backslash' => ['CORP\\salma.h'],
    'an apostrophe' => ["O'Brien"],
    'Arabic with an ampersand' => ['منى & سارة'],
]);
