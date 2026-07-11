<?php

/**
 * Unit tests for the pure front-office account orchestration (Spec 068 US9,
 * FR-158/FR-159). Headless, with a fake AuthGateway — no WordPress runtime.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Profile\Account\AccountResult;
use Corex\Profile\Account\AccountService;
use Corex\Profile\Account\AuthGateway;
use Corex\Profile\Account\RegistrationRequest;

/**
 * A controllable in-memory AuthGateway. Records the last delegated call so tests can
 * assert the service only reaches WordPress after its own validation passes.
 */
final class FakeAuthGateway implements AuthGateway
{
    public bool $registrationOpen = true;
    public bool $emailExists = false;
    public bool $usernameExists = false;
    public int $currentUserId = 0;
    /** @var list<string> */
    public array $calls = [];

    public function registrationOpen(): bool
    {
        return $this->registrationOpen;
    }

    public function emailExists(string $email): bool
    {
        return $this->emailExists;
    }

    public function usernameExists(string $login): bool
    {
        return $this->usernameExists;
    }

    public function createUser(string $email, string $login, string $password): AccountResult
    {
        $this->calls[] = 'createUser';

        return AccountResult::ok('registered', 'ok', 42);
    }

    public function authenticate(string $login, string $password, bool $remember): AccountResult
    {
        $this->calls[] = 'authenticate';

        return AccountResult::ok('logged_in', 'ok', 7);
    }

    public function requestPasswordReset(string $login): AccountResult
    {
        $this->calls[] = 'requestPasswordReset';

        return AccountResult::ok('sent', 'ok');
    }

    public function resetPassword(string $key, string $login, string $password): AccountResult
    {
        $this->calls[] = 'resetPassword';

        return AccountResult::ok('reset', 'ok', 7);
    }

    public function updateProfile(int $userId, array $fields): AccountResult
    {
        $this->calls[] = 'updateProfile';

        return AccountResult::ok('updated', 'ok', $userId);
    }

    public function currentUserId(): int
    {
        return $this->currentUserId;
    }
}

beforeEach(function () {
    // Translation passthrough so the pure service can build messages headlessly.
    Functions\when('__')->returnArg();
});

function makeAccountService(FakeAuthGateway $gateway): AccountService
{
    return new AccountService($gateway);
}

it('registers a valid new account', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        'jane',
        true,
    ));

    expect($result->success)->toBeTrue()
        ->and($result->userId)->toBe(42)
        ->and($gateway->calls)->toBe(['createUser']);
});

it('refuses registration when it is closed', function () {
    $gateway = new FakeAuthGateway();
    $gateway->registrationOpen = false;

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        'jane',
        true,
    ));

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('registration_closed')
        ->and($gateway->calls)->toBe([]); // never reached WordPress
});

it('rejects a mismatched password confirmation', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'different',
        'jane',
        true,
    ));

    expect($result->code)->toBe('password_mismatch')
        ->and($gateway->calls)->toBe([]);
});

it('rejects a password below the minimum length', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'short',
        'short',
        'jane',
        true,
    ));

    expect($result->code)->toBe('weak_password')
        ->and($gateway->calls)->toBe([]);
});

it('requires consent to register', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        'jane',
        false,
    ));

    expect($result->code)->toBe('consent_required')
        ->and($gateway->calls)->toBe([]);
});

it('rejects an already-registered email', function () {
    $gateway = new FakeAuthGateway();
    $gateway->emailExists = true;

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        'jane',
        true,
    ));

    expect($result->code)->toBe('email_taken')
        ->and($gateway->calls)->toBe([]);
});

it('requires both fields to log in', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->login('', 'secret', false);

    expect($result->code)->toBe('empty_credentials')
        ->and($gateway->calls)->toBe([]);
});

it('delegates a valid login to the gateway', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->login('jane', 'secret', true);

    expect($result->success)->toBeTrue()
        ->and($gateway->calls)->toBe(['authenticate']);
});

it('returns a generic reset response that cannot enumerate users', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->requestPasswordReset('jane@example.com');

    // Generic success, but the real request still fired.
    expect($result->success)->toBeTrue()
        ->and($result->code)->toBe('reset_requested')
        ->and($gateway->calls)->toBe(['requestPasswordReset']);
});

it('rejects a reset with an invalid key before touching WordPress', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->resetPassword('', 'jane', 'strongpassword', 'strongpassword');

    expect($result->code)->toBe('invalid_reset')
        ->and($gateway->calls)->toBe([]);
});

it('resets the password when the key and passwords are valid', function () {
    $gateway = new FakeAuthGateway();
    $result = makeAccountService($gateway)->resetPassword('abc123', 'jane', 'strongpassword', 'strongpassword');

    expect($result->success)->toBeTrue()
        ->and($gateway->calls)->toBe(['resetPassword']);
});

it('forbids editing a profile that is not the current user', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;

    $result = makeAccountService($gateway)->updateProfile(9, ['display_name' => 'Jane']);

    expect($result->code)->toBe('forbidden')
        ->and($gateway->calls)->toBe([]);
});

it('updates the current user profile', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;

    $result = makeAccountService($gateway)->updateProfile(7, [
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect($result->success)->toBeTrue()
        ->and($gateway->calls)->toBe(['updateProfile']);
});
