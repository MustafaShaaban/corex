<?php

/**
 * A long email address at registration, asked through the real REST server.
 *
 * With no username typed, the account's login is its email address. WordPress holds a login in
 * 60 characters and an address in 100. An address of 74 characters with no username was answered
 * "Username may not be longer than 60 characters." to somebody who had typed none, and an
 * address of 101 was answered "Your account has been created." with no account stored: WordPress
 * hands back 0 for a row its database did not take (measured 2026-10-13 on WordPress 7.1.3,
 * DECISIONS #338).
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Profile\Account\WordPressAuthGateway;

const LONG_ADDRESS_PASSWORD = 'a-strong-passphrase';

/**
 * Posts the registration form's fields to the add-on's route.
 */
function registerLongAddress(string $email, string $username = ''): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/account/register');
    $request->set_body_params([
        'email'            => $email,
        'username'         => $username,
        'password'         => LONG_ADDRESS_PASSWORD,
        'password_confirm' => LONG_ADDRESS_PASSWORD,
        'consent'          => '1',
    ]);

    return rest_do_request($request);
}

/**
 * An address of exactly that many characters with that first part. The name before
 * `.example.com` is what is made longer, and a name there may have 63 characters.
 */
function addressOfLength(string $firstPart, int $length, string $host = 'mail'): string
{
    $name = str_pad($host, $length - strlen($firstPart . '@.example.com'), 'long');

    return $firstPart . '@' . $name . '.example.com';
}

beforeEach(function () {
    // A registration writes to the new member and to the site's administrator.
    add_filter('pre_wp_mail', '__return_true');
    $this->registration = get_option('users_can_register');
    update_option('users_can_register', '1');

    $this->tag       = strtolower(wp_generate_password(8, false, false));
    $this->firstPart = 'dalia.hassan.' . $this->tag;
    $this->email     = addressOfLength($this->firstPart, 74);
});

afterEach(function () {
    wp_set_current_user(0);
    update_option('users_can_register', $this->registration);

    require_once ABSPATH . 'wp-admin/includes/user.php';
    $made = get_users(['search' => '*' . $this->tag . '*', 'search_columns' => ['user_email', 'user_login'], 'fields' => 'ID']);
    foreach ($made as $userId) {
        wp_delete_user((int) $userId);
    }
    remove_filter('pre_wp_mail', '__return_true');
});

it('registers an address of more than 60 characters with no username', function () {
    $response = registerLongAddress($this->email);
    $user     = get_user_by('email', $this->email);

    expect(strlen($this->email))->toBe(74)
        ->and($response->get_data()['code'] ?? null)->toBe('registered')
        ->and($response->get_status())->toBe(200)
        ->and($user)->toBeInstanceOf(WP_User::class)
        ->and($user->user_login)->toBe($this->firstPart);
});

it('signs that account in with its address', function () {
    registerLongAddress($this->email);

    expect(wp_authenticate($this->email, LONG_ADDRESS_PASSWORD))->toBeInstanceOf(WP_User::class);
});

it('names that account as one with a shorter address is named', function () {
    registerLongAddress($this->email);
    $user = get_user_by('email', $this->email);

    expect($user->display_name)->toBe($this->firstPart)
        ->and($user->nickname)->toBe($this->firstPart)
        ->and($user->user_nicename)->toBe('dalia-hassan-' . $this->tag);
});

it('still makes the login of an address of 60 characters the address', function () {
    $this->email = addressOfLength($this->firstPart, 60);

    registerLongAddress($this->email);

    expect(get_user_by('email', $this->email)->user_login)->toBe($this->email);
});

it('gives a second long address with the same first part a login of its own', function () {
    $second = addressOfLength($this->firstPart, 74, 'post');

    registerLongAddress($this->email);
    $response = registerLongAddress($second);
    $user     = get_user_by('email', $second);

    expect($response->get_status())->toBe(200)
        ->and($user->user_login)->toBe($this->firstPart . '-2')
        ->and($user->display_name)->toBe($this->firstPart)
        ->and(wp_authenticate($second, LONG_ADDRESS_PASSWORD))->toBeInstanceOf(WP_User::class);
});

it('does not give a long address a login somebody chose as their username', function () {
    // What a login keeps of `dalia+shop` is `daliashop`, and that is the name to look for.
    $this->email = addressOfLength('dalia+shop.' . $this->tag, 74);
    registerLongAddress('salma.' . $this->tag . '@example.com', 'daliashop.' . $this->tag);

    $response = registerLongAddress($this->email);

    expect($response->get_status())->toBe(200)
        ->and(get_user_by('email', $this->email)->user_login)->toBe('daliashop.' . $this->tag . '-2');
});

it('makes the login from what a login can hold of the first part, and the name from all of it', function () {
    $this->email = addressOfLength('dalia+shop.' . $this->tag, 74);

    $response = registerLongAddress($this->email);
    $user     = get_user_by('email', $this->email);

    expect($response->get_status())->toBe(200)
        ->and($user->user_login)->toBe('daliashop.' . $this->tag)
        ->and($user->display_name)->toBe('dalia+shop.' . $this->tag);
});

it('cuts a first part that is longer than a login to the 60 characters a login has', function () {
    // The first part of an address may have 64 characters.
    $firstPart   = str_pad('dalia.' . $this->tag . '.', 64, 'a');
    $this->email = $firstPart . '@example.com';

    $response = registerLongAddress($this->email);
    $user     = get_user_by('email', $this->email);

    expect($response->get_status())->toBe(200)
        ->and($user->user_login)->toBe(substr($firstPart, 0, 60))
        ->and($user->display_name)->toBe($firstPart);
});

it('keeps the number of a second login inside the 60 characters', function () {
    $firstPart = str_pad('dalia.' . $this->tag . '.', 64, 'a');
    $second    = $firstPart . '@example.org';

    registerLongAddress($firstPart . '@example.com');
    $response = registerLongAddress($second);

    expect($response->get_status())->toBe(200)
        ->and(get_user_by('email', $second)->user_login)->toBe(substr($firstPart, 0, 58) . '-2');
});

it('asks for a username when a login cannot hold anything of a long address', function () {
    $this->email = addressOfLength('+!+', 61, $this->tag);

    $response = registerLongAddress($this->email);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'] ?? null)->toBe('username_required')
        ->and(email_exists($this->email))->toBeFalse();
});

it('asks for a username when the logins it would make are all taken', function () {
    $taken = array_merge([$this->firstPart], array_map(fn (int $n): string => $this->firstPart . '-' . $n, range(2, 20)));
    foreach ($taken as $login) {
        wp_insert_user(['user_login' => $login, 'user_pass' => LONG_ADDRESS_PASSWORD, 'user_email' => $login . '@example.com']);
    }

    $response = registerLongAddress($this->email);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'] ?? null)->toBe('username_required')
        ->and(email_exists($this->email))->toBeFalse();
});

it('registers that address with the username its owner then types', function () {
    $this->email = addressOfLength('+!+', 61, $this->tag);

    $response = registerLongAddress($this->email, 'Dalia ' . $this->tag);

    expect($response->get_status())->toBe(200)
        ->and(get_user_by('email', $this->email)->user_login)->toBe('Dalia ' . $this->tag);
});

it('refuses an address of more than 100 characters and says how long one may be', function (string $username) {
    $this->email = addressOfLength($this->firstPart . str_repeat('.dalia', 4), 101);
    $username    = $username === '' ? '' : $username . $this->tag;

    $response = registerLongAddress($this->email, $username);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'] ?? null)->toBe('email_too_long')
        ->and($response->get_data()['message'] ?? '')->toContain('100')
        ->and(username_exists($username === '' ? $this->firstPart : $username))->toBeFalse();
})->with([
    'with no username' => [''],
    'with a username'  => ['salma'],
]);

it('registers an address of 100 characters, the longest an account holds', function () {
    $this->email = addressOfLength($this->firstPart . str_repeat('.dalia', 4), 100);

    $response = registerLongAddress($this->email);

    expect($response->get_status())->toBe(200)
        ->and(get_user_by('login', $this->firstPart . str_repeat('.dalia', 4))->user_email)->toBe($this->email);
});

it('does not say an account was created when WordPress stored none', function () {
    $tooLong = addressOfLength($this->firstPart . str_repeat('.dalia', 4), 101);

    $result = (new WordPressAuthGateway())->createUser($tooLong, 'salma' . $this->tag, LONG_ADDRESS_PASSWORD, 'Salma');

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('register_failed')
        ->and(username_exists('salma' . $this->tag))->toBeFalse();
});

it('refuses to change an account to an address of more than 100 characters, and changes nothing', function () {
    $userId = (int) wp_insert_user([
        'user_login'   => 'salma' . $this->tag,
        'user_pass'    => LONG_ADDRESS_PASSWORD,
        'user_email'   => 'salma' . $this->tag . '@example.com',
        'display_name' => 'Before',
    ]);
    wp_set_current_user($userId);
    $request = new WP_REST_Request('POST', '/corex/v1/account/profile');
    $request->set_body_params([
        'display_name' => 'After',
        'email'        => addressOfLength($this->firstPart . str_repeat('.dalia', 4), 101),
    ]);

    $response = rest_do_request($request);
    clean_user_cache($userId);
    $user = get_userdata($userId);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'] ?? null)->toBe('email_too_long')
        ->and($user->user_email)->toBe('salma' . $this->tag . '@example.com')
        ->and($user->display_name)->toBe('Before');
});
