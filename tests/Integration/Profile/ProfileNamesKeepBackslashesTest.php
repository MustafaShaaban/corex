<?php

/**
 * A person's names are kept as that person wrote them.
 *
 * WordPress takes one level of slashes off a user's fields and off a user's meta on their way
 * in, and the account gateway handed a display name, a first name and a last name over as they
 * were: `CORP\salma.h` was saved as `CORPsalma.h` (measured 2026-10-13, DECISIONS #332).
 *
 * On real WordPress, through the account service the profile route calls. A password is not
 * handed over here and is not touched by the change.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Profile\Account\AccountService;
use Corex\Tests\Support\BackslashedText;

beforeEach(function () {
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $login = 'kept' . strtolower(wp_generate_password(10, false));
    $this->password = wp_generate_password(24);
    $this->userId = (int) wp_insert_user([
        'user_login' => $login,
        'user_pass' => $this->password,
        'user_email' => $login . '@example.com',
    ]);
    wp_set_current_user($this->userId);
    $this->accounts = Boot::app()->container()->make(AccountService::class);
});

afterEach(function () {
    wp_set_current_user(0);
    wp_delete_user($this->userId);
});

it('saves a display, first and last name as they were written', function (string $text) {
    $saved = $this->accounts->updateProfile($this->userId, [
        'display_name' => $text,
        'first_name' => $text,
        'last_name' => $text,
    ]);
    $user = get_userdata($this->userId);

    expect($saved->success)->toBeTrue()
        ->and($user->display_name)->toBe($text)
        ->and($user->first_name)->toBe($text)
        ->and($user->last_name)->toBe($text);
})->with(BackslashedText::cases());

it('leaves the email address and the password of a profile whose names are saved', function () {
    $before = get_userdata($this->userId);

    $this->accounts->updateProfile($this->userId, ['display_name' => 'CORP\\salma.h', 'email' => $before->user_email]);
    $after = get_userdata($this->userId);

    expect($after->user_email)->toBe($before->user_email)
        ->and($after->user_pass)->toBe($before->user_pass)
        ->and(wp_check_password($this->password, $after->user_pass, $this->userId))->toBeTrue();
});
