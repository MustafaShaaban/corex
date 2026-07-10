<?php

/**
 * Integration test: the front-office account lifecycle on real ./wp (Spec 068 US9,
 * FR-158/FR-159). Exercises registration, duplicate protection, credential validity,
 * profile editing, generic password-reset, session listing, and the registration gate
 * through the real WordPress auth gateway. Mail is intercepted.
 *
 * @package Corex\Tests\Integration\Profile
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Profile\Account\AccountService;
use Corex\Profile\Account\RegistrationRequest;
use Corex\Profile\Session\SessionService;

it('runs the account lifecycle through the real WordPress auth gateway', function () {
    add_filter('pre_wp_mail', '__return_true');
    $previousRegistration = get_option('users_can_register');
    update_option('users_can_register', 1);

    $container = Boot::app()->container();
    $accounts  = $container->make(AccountService::class);
    $sessions  = $container->make(SessionService::class);

    $email    = 'acct-' . uniqid() . '@example.com';
    $username = 'acct_' . substr(uniqid(), -8);
    $password = 'a-strong-passphrase';

    // Register.
    $registered = $accounts->register(new RegistrationRequest($email, $password, $password, $username, true));
    expect($registered->success)->toBeTrue()
        ->and($registered->userId)->toBeGreaterThan(0);

    $userId = (int) $registered->userId;
    $user   = get_userdata($userId);
    expect($user)->not->toBeFalse()
        ->and($user->user_email)->toBe($email)
        // Credentials are valid — the account can sign in (checked without setting cookies).
        ->and(wp_check_password($password, $user->user_pass, $userId))->toBeTrue();

    // Duplicate email is refused before touching WordPress a second time.
    $dupe = $accounts->register(new RegistrationRequest($email, $password, $password, 'other_' . $username, true));
    expect($dupe->success)->toBeFalse()
        ->and($dupe->code)->toBe('email_taken');

    // Profile edit (as the acting user) persists.
    wp_set_current_user($userId);
    $updated = $accounts->updateProfile($userId, ['display_name' => 'Jane Renamed']);
    expect($updated->success)->toBeTrue()
        ->and(get_userdata($userId)->display_name)->toBe('Jane Renamed');

    // Password reset request is generic (no user enumeration).
    $reset = $accounts->requestPasswordReset($email);
    expect($reset->success)->toBeTrue()
        ->and($reset->code)->toBe('reset_requested');

    // Sessions list is a safe array (no token/verifier leak).
    $active = $sessions->active($userId);
    expect($active)->toBeArray();
    foreach ($active as $session) {
        expect($session)->toHaveKeys(['current', 'login', 'expiration', 'ip', 'ua'])
            ->and($session)->not->toHaveKey('verifier');
    }

    // Cleanup + restore.
    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($userId);
    update_option('users_can_register', $previousRegistration);
    remove_all_filters('pre_wp_mail');
});

it('refuses registration when the site has it disabled', function () {
    $previousRegistration = get_option('users_can_register');
    update_option('users_can_register', 0);

    $accounts = Boot::app()->container()->make(AccountService::class);
    $result   = $accounts->register(new RegistrationRequest(
        'closed-' . uniqid() . '@example.com',
        'a-strong-passphrase',
        'a-strong-passphrase',
        '',
        true,
    ));

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('registration_closed');

    update_option('users_can_register', $previousRegistration);
});
