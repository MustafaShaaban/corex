<?php

/**
 * The account block's list of active sessions says which one the person is using.
 *
 * It never did: every row of `GET corex/v1/account/sessions` carried `"current": false`, for
 * somebody with one session too (measured on 2026-10-13, WordPress 7.1.3). The rows were looked
 * up by a key WordPress does not hand over: `WP_Session_Tokens::get_all()` is a plain list. A
 * unit test that handed the formatter a keyed array passed all the while, so this one signs a
 * person in on real WordPress and asks the route (spec 068 FR-161h, DECISIONS #349).
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

const SESSION_TEST_IP = '198.51.100.91';

/**
 * Signs the test's person in from a browser, as `wp_signon()` does, and gives back the cookie
 * that browser would hold. Nothing is sent: a test run's headers have gone.
 */
function signsInFrom(int $userId, string $userAgent): string
{
    $cookie = '';
    $keep   = static function (string $value) use (&$cookie): void {
        $cookie = $value;
    };

    $_SERVER['HTTP_USER_AGENT'] = $userAgent;
    add_action('set_logged_in_cookie', $keep);
    wp_set_auth_cookie($userId);
    remove_action('set_logged_in_cookie', $keep);

    return $cookie;
}

/**
 * What the route answers a request that carries this cookie.
 *
 * @return list<array<string, mixed>>
 */
function sessionsListedFor(string $cookie): array
{
    $_COOKIE[LOGGED_IN_COOKIE] = $cookie;

    $response = rest_do_request(new WP_REST_Request('GET', '/corex/v1/account/sessions'));

    return $response->get_data()['sessions'];
}

/**
 * @param list<array<string, mixed>> $sessions
 *
 * @return list<array<string, mixed>>
 */
function sessionsMarkedCurrent(array $sessions): array
{
    return array_values(array_filter($sessions, static fn (array $session): bool => $session['current']));
}

beforeEach(function () {
    add_filter('send_auth_cookies', '__return_false');

    $this->server           = $_SERVER;
    $this->cookies          = $_COOKIE;
    $_SERVER['REMOTE_ADDR'] = SESSION_TEST_IP;

    $login        = 'corexsession' . strtolower(wp_generate_password(8, false, false));
    $this->userId = (int) wp_insert_user([
        'user_login' => $login,
        'user_email' => $login . '@example.com',
        'user_pass'  => wp_generate_password(24),
    ]);
    wp_set_current_user($this->userId);
});

afterEach(function () {
    remove_filter('send_auth_cookies', '__return_false');
    wp_set_current_user(0);

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->userId);

    $_SERVER = $this->server;
    $_COOKIE = $this->cookies;
});

it('marks the only session of somebody who is signed in', function () {
    $cookie = signsInFrom($this->userId, 'Browser on the laptop');

    $sessions = sessionsListedFor($cookie);

    expect($sessions)->toHaveCount(1)
        ->and($sessions[0]['current'])->toBeTrue();
});

it('marks the session of the browser that asks, and no other', function () {
    $laptop = signsInFrom($this->userId, 'Browser on the laptop');
    $phone  = signsInFrom($this->userId, 'Browser on the phone');

    $askedFromLaptop = sessionsMarkedCurrent(sessionsListedFor($laptop));
    $askedFromPhone  = sessionsMarkedCurrent(sessionsListedFor($phone));

    expect($askedFromLaptop)->toHaveCount(1)
        ->and($askedFromLaptop[0]['ua'])->toBe('Browser on the laptop')
        ->and($askedFromPhone)->toHaveCount(1)
        ->and($askedFromPhone[0]['ua'])->toBe('Browser on the phone');
});

it('marks one of two sessions WordPress holds the same record for', function () {
    $cookie  = signsInFrom($this->userId, 'Browser on the laptop');
    $token   = wp_parse_auth_cookie($cookie, 'logged_in')['token'];
    $manager = WP_Session_Tokens::get_instance($this->userId);
    // A second sign-in in the same second, from the same address and browser.
    $manager->update('a-second-token-with-the-same-record', $manager->get($token));

    $sessions = sessionsListedFor($cookie);

    expect($sessions)->toHaveCount(2)
        ->and(sessionsMarkedCurrent($sessions))->toHaveCount(1);
});

it('marks none for a request that is nobody\'s browser session', function () {
    signsInFrom($this->userId, 'Browser on the laptop');
    // An application password, or WP-CLI: a person, and no session cookie.
    unset($_COOKIE[LOGGED_IN_COOKIE]);

    $response = rest_do_request(new WP_REST_Request('GET', '/corex/v1/account/sessions'));
    $sessions = $response->get_data()['sessions'];

    expect($sessions)->toHaveCount(1)
        ->and(sessionsMarkedCurrent($sessions))->toBe([]);
});

it('hands over nothing a session could be taken with', function () {
    $cookie = signsInFrom($this->userId, 'Browser on the laptop');
    $token  = wp_parse_auth_cookie($cookie, 'logged_in')['token'];

    $sessions = sessionsListedFor($cookie);

    expect(array_keys($sessions[0]))->toBe(['current', 'login', 'expiration', 'ip', 'ua', 'device', 'signedIn'])
        ->and(wp_json_encode($sessions))->not->toContain($token)
        ->and(wp_json_encode($sessions))->not->toContain(hash('sha256', $token));
});
