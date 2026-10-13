<?php

/**
 * A password with a quote or a backslash in it is one password at the account add-on and at
 * WordPress's own screens.
 *
 * WordPress's screens hash a password with the slashes of the request still on it, and check it
 * the same way. The add-on handed over the password of a REST request, which has none: a
 * password chosen through the add-on was refused at `wp-login.php`, and one chosen on a
 * WordPress screen was refused by the add-on (measured over HTTP on 2026-10-13, WordPress 7.1.3,
 * DECISIONS #333). The owner's decision of the same day is #334: the add-on hashes it
 * WordPress's way, and an account that holds the other form is moved when its owner signs in.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Database\Schema\Migrator;
use Corex\Profile\Account\AccountService;
use Corex\Profile\Account\RegistrationRequest;

/**
 * The address every sign-in here is made from, in a range reserved for documentation: what the
 * sign-in limiter records against it is these tests' and nobody else's.
 */
const PASSWORD_TEST_IP = '198.51.100.77';

const PASSWORD_WITH_QUOTES = 'it\'s a "pass" word';

/**
 * What `wp-login.php` does with the password field of its form: `wp_signon()` hands
 * `wp_authenticate()` `$_POST['pwd']`, which still has the request's slashes on it.
 */
function signsInAtWordPressScreen(string $login, string $password): bool
{
    return wp_authenticate($login, wp_slash($password)) instanceof WP_User;
}

/**
 * An account with a password nobody knows, for a test to set the one it is about.
 */
function accountWithoutKnownPassword(string $login): int
{
    return (int) wp_insert_user([
        'user_login' => $login,
        'user_email' => $login . '@example.com',
        'user_pass'  => wp_generate_password(24),
    ]);
}

beforeEach(function () {
    // A registration and a changed password each write to somebody.
    add_filter('pre_wp_mail', '__return_true');
    // A sign-in sets cookies, and the headers of a test run have gone.
    add_filter('send_auth_cookies', '__return_false');

    $this->registration = get_option('users_can_register');
    update_option('users_can_register', '1');
    $this->server           = $_SERVER;
    $_SERVER['REMOTE_ADDR'] = PASSWORD_TEST_IP;
    // WordPress reads it when it checks a cookie, and a test run has none.
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $this->failedSignIns = 0;
    $this->countFailure  = function (): void {
        $this->failedSignIns++;
    };
    add_action('wp_login_failed', $this->countFailure);

    $this->cookie     = '';
    $this->keepCookie = function (string $cookie): void {
        $this->cookie = $cookie;
    };
    add_action('set_logged_in_cookie', $this->keepCookie);

    $this->login    = 'corexpass' . strtolower(wp_generate_password(8, false, false));
    $this->accounts = Boot::app()->container()->make(AccountService::class);
});

afterEach(function () {
    global $wpdb;

    remove_filter('pre_wp_mail', '__return_true');
    remove_filter('send_auth_cookies', '__return_false');
    remove_action('wp_login_failed', $this->countFailure);
    remove_action('set_logged_in_cookie', $this->keepCookie);
    update_option('users_can_register', $this->registration);
    wp_set_current_user(0);

    $user = get_user_by('login', $this->login);
    if ($user instanceof WP_User) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user->ID);
    }

    $wpdb->delete(
        (new Migrator())->fullName(LoginAttemptTable::NAME),
        ['network_hash' => hash('sha256', PASSWORD_TEST_IP)],
    );
    $_SERVER = $this->server;
});

dataset('passwords WordPress slashes', [
    'quotes'      => [PASSWORD_WITH_QUOTES],
    'a backslash' => ['back\slash-123'],
]);

it('hashes a password chosen at registration the way a WordPress screen does', function (string $password) {
    $registered = $this->accounts->register(
        new RegistrationRequest($this->login . '@example.com', $password, $password, $this->login, true),
    );

    expect($registered->success)->toBeTrue()
        ->and(signsInAtWordPressScreen($this->login, $password))->toBeTrue()
        ->and($this->accounts->login($this->login, $password, false)->success)->toBeTrue();
})->with('passwords WordPress slashes');

it('hashes a password chosen through the reset route the way a WordPress screen does', function (string $password) {
    $user = get_userdata(accountWithoutKnownPassword($this->login));

    $reset = $this->accounts->resetPassword(get_password_reset_key($user), $this->login, $password, $password);

    expect($reset->success)->toBeTrue()
        ->and(signsInAtWordPressScreen($this->login, $password))->toBeTrue()
        ->and($this->accounts->login($this->login, $password, false)->success)->toBeTrue();
})->with('passwords WordPress slashes');

it('signs in somebody whose password was set on a WordPress screen', function (string $password) {
    wp_set_password(wp_slash($password), accountWithoutKnownPassword($this->login));

    expect($this->accounts->login($this->login, $password, false)->success)->toBeTrue();
})->with('passwords WordPress slashes');

it('still signs in an account that holds the password as it was typed, and moves it', function (string $password) {
    // What the add-on stored until #334.
    $userId = accountWithoutKnownPassword($this->login);
    wp_set_password($password, $userId);

    $signedIn = $this->accounts->login($this->login, $password, false);

    expect($signedIn->success)->toBeTrue()
        ->and(signsInAtWordPressScreen($this->login, $password))->toBeTrue()
        ->and($this->failedSignIns)->toBe(0);
})->with('passwords WordPress slashes');

it('leaves the browser that moved an account signed in, with one session', function () {
    $password = PASSWORD_WITH_QUOTES;
    $userId   = accountWithoutKnownPassword($this->login);
    wp_set_password($password, $userId);

    $this->accounts->login($this->login, $password, false);

    // The cookie is made from the stored hash, which the move has just replaced.
    expect(wp_validate_auth_cookie($this->cookie, 'logged_in'))->toBe($userId)
        ->and(WP_Session_Tokens::get_instance($userId)->get_all())->toHaveCount(1);
});

it('does not move an account whose sign-in something else refuses', function () {
    $password = PASSWORD_WITH_QUOTES;
    $userId   = accountWithoutKnownPassword($this->login);
    wp_set_password($password, $userId);
    $stored = get_userdata($userId)->user_pass;

    // Where the sign-in limiter refuses somebody who is locked out, right password or not.
    $lockedOut = static fn (): WP_Error => new WP_Error('corex_login_locked', 'Locked out.');
    add_filter('authenticate', $lockedOut, 30);
    $refused = $this->accounts->login($this->login, $password, false);
    remove_filter('authenticate', $lockedOut, 30);

    expect($refused->success)->toBeFalse()
        ->and(get_userdata($userId)->user_pass)->toBe($stored)
        ->and($this->failedSignIns)->toBe(1);
});

it('moves such an account when its owner signs in with the address', function () {
    $password = PASSWORD_WITH_QUOTES;
    wp_set_password($password, accountWithoutKnownPassword($this->login));

    $signedIn = $this->accounts->login($this->login . '@example.com', $password, false);

    expect($signedIn->success)->toBeTrue()
        ->and(signsInAtWordPressScreen($this->login, $password))->toBeTrue();
});

it('counts a wrong password as one failed sign-in and leaves the account as it was', function () {
    $userId = accountWithoutKnownPassword($this->login);
    wp_set_password(PASSWORD_WITH_QUOTES, $userId);
    $stored = get_userdata($userId)->user_pass;

    $refused = $this->accounts->login($this->login, 'it\'s "not" the word', false);

    expect($refused->success)->toBeFalse()
        ->and($this->failedSignIns)->toBe(1)
        ->and(get_userdata($userId)->user_pass)->toBe($stored);
});
