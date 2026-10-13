<?php

/**
 * The name a new account goes by, asked through the real REST server.
 *
 * With no username typed, the account's login is its email address, and the add-on handed that
 * login to WordPress as the display name. WordPress makes the nickname and the address of the
 * author page from the login too. A comment by `dalia.hassan@example.com` was signed
 * `dalia.hassan@example.com` on the post's page, in `wp/v2/comments` and in the comments feed,
 * for a visitor who was not signed in (measured 2026-10-13 on WordPress 7.1.3, DECISIONS #335).
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

const PUBLIC_NAME_PASSWORD = 'a-strong-passphrase';

/**
 * Posts the registration form's fields to the add-on's route.
 */
function registerForPublicName(string $email, string $username): WP_REST_Response
{
    $request = new WP_REST_Request('POST', '/corex/v1/account/register');
    $request->set_body_params([
        'email'            => $email,
        'username'         => $username,
        'password'         => PUBLIC_NAME_PASSWORD,
        'password_confirm' => PUBLIC_NAME_PASSWORD,
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
    $this->host  = 'mail-' . $this->tag;
    $this->email = 'dalia.' . $this->tag . '@' . $this->host . '.example.com';
    $this->postId = (int) wp_insert_post(['post_title' => 'Open to comments ' . $this->tag, 'post_status' => 'publish']);
});

afterEach(function () {
    unset($_SERVER['REMOTE_ADDR']);
    wp_set_current_user(0);
    update_option('users_can_register', $this->registration);

    // The post takes its comments with it.
    wp_delete_post($this->postId, true);
    $userId = email_exists($this->email);
    if ($userId !== false) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user((int) $userId);
    }
    remove_filter('pre_wp_mail', '__return_true');
});

it('names an account registered with no username by the part of its address before the @', function () {
    $response = registerForPublicName($this->email, '');
    $user     = get_user_by('email', $this->email);

    expect($response->get_status())->toBe(200)
        ->and($user->display_name)->toBe('dalia.' . $this->tag)
        ->and($user->nickname)->toBe('dalia.' . $this->tag)
        ->and($user->user_nicename)->toBe('dalia-' . $this->tag);
});

it('still signs that account in with its address', function () {
    registerForPublicName($this->email, '');

    expect(wp_authenticate($this->email, PUBLIC_NAME_PASSWORD))->toBeInstanceOf(WP_User::class);
});

it('signs a comment by that account with the name, and gives a visitor none of the address', function () {
    registerForPublicName($this->email, '');
    wp_set_current_user((int) email_exists($this->email));
    // WordPress records where a comment came from, and the command line comes from nowhere.
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    $comment = wp_handle_comment_submission([
        'comment_post_ID' => $this->postId,
        'comment'         => 'A comment by ' . $this->tag,
    ]);
    wp_set_comment_status((int) $comment->comment_ID, 'approve');
    wp_set_current_user(0);
    $asVisitor = rest_do_request(new WP_REST_Request('GET', '/wp/v2/comments/' . $comment->comment_ID));
    $classes   = implode(' ', get_comment_class('', (int) $comment->comment_ID, $this->postId));

    expect($asVisitor->get_status())->toBe(200)
        ->and($asVisitor->get_data()['author_name'])->toBe('dalia.' . $this->tag)
        ->and(wp_json_encode($asVisitor->get_data()))->not->toContain($this->host)
        ->and($classes)->toContain('comment-author-dalia-' . $this->tag)
        ->and($classes)->not->toContain($this->host);
});

it('names an account by the username somebody typed, as before', function () {
    $username = 'Salma K ' . $this->tag;

    registerForPublicName($this->email, $username);
    $user = get_user_by('email', $this->email);

    expect($user->user_login)->toBe($username)
        ->and($user->display_name)->toBe($username)
        ->and($user->nickname)->toBe($username)
        ->and($user->user_nicename)->toBe('salma-k-' . $this->tag);
});

it('does not name an account by an email address typed as its username', function () {
    registerForPublicName($this->email, 'salma.' . $this->tag . '@example.org');
    $user = get_user_by('email', $this->email);

    expect($user->user_login)->toBe('salma.' . $this->tag . '@example.org')
        ->and($user->display_name)->toBe('salma.' . $this->tag)
        ->and($user->nickname)->toBe('salma.' . $this->tag)
        ->and($user->user_nicename)->toBe('salma-' . $this->tag);
});

it('registers an address whose first part is longer than an author address can be', function () {
    // WordPress refuses a nicename over 50 characters, and a login over 60.
    $this->email = str_pad('dalia.' . $this->tag . '.', 52, 'a') . '@ex.co';

    $response = registerForPublicName($this->email, '');
    $user     = get_user_by('email', $this->email);

    expect($response->get_status())->toBe(200)
        ->and($user->display_name)->toBe(strstr($this->email, '@', true))
        ->and($user->user_nicename)->toBe(str_pad('dalia-' . $this->tag . '-', 50, 'a'));
});

it('registers an address whose first part has nothing an author address can hold', function () {
    // WordPress refuses an account whose nicename would be empty, so this one keeps the
    // nicename WordPress makes from the login.
    $this->email = '+!+@' . $this->tag . '.example.com';

    $response = registerForPublicName($this->email, '');
    $user     = get_user_by('email', $this->email);

    expect($response->get_status())->toBe(200)
        ->and($user->display_name)->toBe('+!+')
        ->and($user->user_nicename)->toBe($this->tag . '-example-com');
});
