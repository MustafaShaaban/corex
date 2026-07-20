<?php

/**
 * Integration tests for saving the login policy over REST.
 *
 * The save route is the last place an owner can be stopped from locking themselves out, and it had
 * no validation at all — it accepted any slug and let the store quietly substitute a working one.
 * A refusal that explains itself is the difference between "I typed something wrong" and "the
 * button does nothing".
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\SecuritySettingsController;

beforeEach(function () {
    $this->previousLoginSettings = get_option(LoginProtectionSettingsStore::OPTION, null);
    $this->store = new LoginProtectionSettingsStore();
    $this->controller = new SecuritySettingsController($this->store);

    wp_set_current_user((int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0]);
});

afterEach(function () {
    wp_set_current_user(0);

    if ($this->previousLoginSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);

        return;
    }

    update_option(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
});

function corexSaveRequest(array $body): WP_REST_Request
{
    $request = new WP_REST_Request('POST', '/corex/v1/security/login-protection');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body((string) wp_json_encode($body));

    return $request;
}

it('saves a valid login address and reports where the login now lives', function () {
    $response = $this->controller->save(corexSaveRequest([
        'enabled' => true,
        'custom_slug' => 'team-entry',
        'block_default_endpoints' => true,
    ]));

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['login_protection']['custom_slug'])->toBe('team-entry')
        ->and($this->store->current()->customSlug)->toBe('team-entry');
});

it('refuses an unusable login address with a reason, and changes nothing', function (string $slug) {
    $this->store->save(['enabled' => true, 'custom_slug' => 'team-entry']);

    $response = $this->controller->save(corexSaveRequest([
        'enabled' => true,
        'custom_slug' => $slug,
        'block_default_endpoints' => true,
    ]));

    expect($response->get_status())->toBe(400)
        ->and($response->get_data()['message'])->toBeString()->not->toBe('')
        // The previous working configuration survives a refused save.
        ->and($this->store->current()->customSlug)->toBe('team-entry');
})->with([
    'empty' => '',
    'unsanitizable' => '!!!',
    'too short' => 'ab',
    'reserved: wp-admin' => 'wp-admin',
    // /login is redirected by core, and the guard removes that redirect — taking it as the login
    // address would put the login where core also claims to route.
    'reserved: login' => 'login',
    'reserved: wp-login' => 'wp-login',
]);

it('explains the refusal differently depending on what is wrong', function () {
    $tooShort = $this->controller->save(corexSaveRequest(['enabled' => true, 'custom_slug' => 'ab']));
    $reserved = $this->controller->save(corexSaveRequest(['enabled' => true, 'custom_slug' => 'wp-admin']));

    expect($tooShort->get_data()['message'])->not->toBe($reserved->get_data()['message']);
});

it('normalises an address the owner could reasonably type', function (string $typed, string $stored) {
    $response = $this->controller->save(corexSaveRequest(['enabled' => true, 'custom_slug' => $typed]));

    expect($response->get_status())->toBe(200)
        ->and($this->store->current()->customSlug)->toBe($stored);
})->with([
    'uppercase' => ['Team-Entry', 'team-entry'],
    'spaces' => ['team entry', 'team-entry'],
    'surrounding slashes' => ['/team-entry/', 'team-entry'],
]);

it('defaults the address when none is supplied rather than refusing', function () {
    $response = $this->controller->save(corexSaveRequest(['enabled' => true]));

    expect($response->get_status())->toBe(200)
        ->and($this->store->current()->customSlug)->toBe(LoginSlug::DEFAULT);
});
