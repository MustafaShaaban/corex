<?php

/**
 * The add-on endpoints that take an email address, asked through the real REST server.
 *
 * Each of them called `sanitize_email()` on what was posted and handed the result to a service
 * that then found it valid — because it had just been made valid. `sal,ma@example.com` became
 * `salma@example.com`: a newsletter subscription, or an account, for an address nobody typed
 * (2026-10-07, the same defect DECISIONS #245 fixed in the forms engine).
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Newsletter\Subscriber\SubscriberRepository;
use Corex\Newsletter\Subscriber\SubscriberStore;
use Corex\Tests\Support\CreatedPosts;

/**
 * A mistyped address and the one `sanitize_email()` would have turned it into.
 *
 * @return array{typed:string,rewritten:string}
 */
function mistypedAddress(): array
{
    $tag = 'corex-typo-' . wp_generate_password(8, false, false);

    return ['typed' => strtolower($tag) . ',x@example.com', 'rewritten' => strtolower($tag) . 'x@example.com'];
}

beforeEach(function () {
    // Neither endpoint should get as far as sending anything; if one does, it is not delivered.
    add_filter('pre_wp_mail', '__return_true');
    $this->emailLogs = CreatedPosts::watch('corex_email_log');
    $this->address   = mistypedAddress();
});

afterEach(function () {
    remove_filter('pre_wp_mail', '__return_true');
    $this->emailLogs->delete();

    // What a regression would have created, removed by the address only it could have used.
    $container = Boot::app()->container();
    if ($container->has(SubscriberStore::class)) {
        $subscriber = $container->make(SubscriberStore::class)->findByEmail($this->address['rewritten']);
        if ($subscriber !== null) {
            $container->make(SubscriberRepository::class)->delete($subscriber['id']);
        }
    }
    $userId = email_exists($this->address['rewritten']);
    if ($userId !== false) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user((int) $userId);
    }
});

it('does not subscribe the address a mistyped one would be cleaned into', function () {
    $request = new WP_REST_Request('POST', '/corex/v1/newsletter/subscribe');
    $request->set_body_params(['email' => $this->address['typed'], 'topics' => ['news'], 'consent' => '1']);

    $response = rest_do_request($request);

    $store = Boot::app()->container()->make(SubscriberStore::class);

    expect($response->get_status())->toBe(422)
        ->and($store->findByEmail($this->address['rewritten']))->toBeNull()
        ->and($store->findByEmail($this->address['typed']))->toBeNull();
});

it('does not register an account for the address a mistyped one would be cleaned into', function () {
    $previous = get_option('users_can_register');
    update_option('users_can_register', '1');

    $request = new WP_REST_Request('POST', '/corex/v1/account/register');
    $request->set_body_params([
        'email'            => $this->address['typed'],
        'username'         => 'corex-typo-' . wp_generate_password(6, false, false),
        'password'         => 'Correct-horse-battery-9!',
        'password_confirm' => 'Correct-horse-battery-9!',
        'consent'          => '1',
    ]);

    $response = rest_do_request($request);

    update_option('users_can_register', $previous);

    expect($response->get_data()['code'] ?? null)->toBe('invalid_email')
        ->and(email_exists($this->address['rewritten']))->toBeFalse();
});
