<?php

/**
 * A username at registration, asked through the real REST server.
 *
 * WordPress does not refuse a login it does not accept, it stores what is left of it:
 * `CORP\salma` is stored as `CORPsalma`. The add-on handed over what was typed and answered that
 * the account was created, and the person could not sign in with the name they had chosen
 * (measured 2026-10-13 on WordPress 7.1.3, DECISIONS #333).
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

const REGISTRATION_PASSWORD = 'a-strong-passphrase';

/**
 * Posts the registration form's fields to the add-on's route.
 */
function registerAccount(string $email, string $username): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/account/register');
    $request->set_body_params([
        'email'            => $email,
        'username'         => $username,
        'password'         => REGISTRATION_PASSWORD,
        'password_confirm' => REGISTRATION_PASSWORD,
        'consent'          => '1',
    ]);

    return rest_do_request($request);
}

beforeEach(function () {
    // A registration writes to the new member and to the site's administrator.
    add_filter('pre_wp_mail', '__return_true');
    $this->registration = get_option('users_can_register');
    update_option('users_can_register', '1');

    $this->tag   = strtolower(wp_generate_password(8, false, false));
    $this->email = 'corex-login-' . $this->tag . '@example.com';
});

afterEach(function () {
    remove_filter('pre_wp_mail', '__return_true');
    update_option('users_can_register', $this->registration);

    $userId = email_exists($this->email);
    if ($userId !== false) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user((int) $userId);
    }
});

it('refuses a username WordPress would store as a different one', function (string $username) {
    $response = registerAccount($this->email, $username . $this->tag);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['code'] ?? null)->toBe('invalid_username')
        ->and(email_exists($this->email))->toBeFalse();
})->with([
    'a backslash'    => ['CORP\salma'],
    'an accent'      => ['sälma'],
    'markup'         => ['<b>salma</b>'],
    'Arabic letters' => ['سلمى'],
]);

it('registers a username WordPress keeps, and the person signs in with it', function () {
    $username = 'Salma K.-_@' . $this->tag;

    $response = registerAccount($this->email, $username);

    expect($response->get_status())->toBe(200)
        ->and(get_user_by('email', $this->email)->user_login)->toBe($username)
        ->and(wp_authenticate($username, REGISTRATION_PASSWORD))->toBeInstanceOf(WP_User::class);
});

it('takes the spaces around a username off and does not refuse it for them', function () {
    $response = registerAccount($this->email, '  salma' . $this->tag . ' ');

    expect($response->get_status())->toBe(200)
        ->and(get_user_by('email', $this->email)->user_login)->toBe('salma' . $this->tag);
});

it('still registers somebody who typed no username, whatever their address holds', function () {
    // The login is then made from the address, and WordPress takes the plus out of it. Nobody
    // typed that login, and the person signs in with the address.
    $this->email = 'corex-login-' . $this->tag . '+shop@example.com';

    $response = registerAccount($this->email, '');

    expect($response->get_status())->toBe(200)
        ->and(wp_authenticate($this->email, REGISTRATION_PASSWORD))->toBeInstanceOf(WP_User::class);
});
