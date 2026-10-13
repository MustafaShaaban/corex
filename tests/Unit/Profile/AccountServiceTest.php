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
    public bool $acceptsUsername = true;
    /** What the gateway answers for an address; null for the address itself. */
    public ?string $loginForAddress = null;
    public int $currentUserId = 0;
    /** What the account would hold for a display name; null when that is the name as typed. */
    public ?string $displayNameAsKept = null;
    /** @var list<string> */
    public array $calls = [];
    /** @var array{login: string, name: string}|null */
    public ?array $created = null;

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

    public function acceptsUsername(string $username): bool
    {
        return $this->acceptsUsername;
    }

    public function loginForAddress(string $email): string
    {
        return $this->loginForAddress ?? $email;
    }

    public function createUser(string $email, string $login, string $password, string $name): AccountResult
    {
        $this->calls[] = 'createUser';
        $this->created = ['login' => $login, 'name' => $name];

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

    public function displayNameAsKept(string $name): string
    {
        return $this->displayNameAsKept ?? $name;
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

it('refuses a username WordPress would store as a different one', function () {
    $gateway = new FakeAuthGateway();
    $gateway->acceptsUsername = false;

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        'jane!',
        true,
    ));

    expect($result->code)->toBe('invalid_username')
        ->and($gateway->calls)->toBe([]);
});

it('gives an account with no username the login the gateway has for its address, and names it by the address', function () {
    $gateway = new FakeAuthGateway();
    $gateway->loginForAddress = 'janeshop-2';

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane+shop@example.com',
        'strongpassword',
        'strongpassword',
        '',
        true,
    ));

    expect($result->success)->toBeTrue()
        ->and($gateway->created)->toBe(['login' => 'janeshop-2', 'name' => 'jane+shop']);
});

it('names an account by the username somebody typed', function () {
    $gateway = new FakeAuthGateway();
    $gateway->loginForAddress = 'never-asked-for';

    makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        'Jane D',
        true,
    ));

    expect($gateway->created)->toBe(['login' => 'Jane D', 'name' => 'Jane D']);
});

it('asks for a username when the gateway has no login for the address', function () {
    $gateway = new FakeAuthGateway();
    $gateway->loginForAddress = '';

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        'jane@example.com',
        'strongpassword',
        'strongpassword',
        '',
        true,
    ));

    expect($result->code)->toBe('username_required')
        ->and($gateway->calls)->toBe([]);
});

it('refuses an address longer than an account holds', function () {
    $gateway = new FakeAuthGateway();
    $address = str_repeat('a', 60) . '@' . str_repeat('b', 28) . '.example.com';

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        $address,
        'strongpassword',
        'strongpassword',
        'jane',
        true,
    ));

    expect(strlen($address))->toBe(101)
        ->and($result->code)->toBe('email_too_long')
        ->and($result->message)->toContain('100')
        ->and($gateway->calls)->toBe([]);
});

it('registers an address of the longest an account holds', function () {
    $gateway = new FakeAuthGateway();

    $result = makeAccountService($gateway)->register(new RegistrationRequest(
        str_repeat('a', 60) . '@' . str_repeat('b', 27) . '.example.com',
        'strongpassword',
        'strongpassword',
        'jane',
        true,
    ));

    expect($result->success)->toBeTrue();
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

it('saves a display name of the longest an account holds', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;

    $result = makeAccountService($gateway)->updateProfile(7, [
        'display_name' => str_repeat('n', AccountService::MAX_DISPLAY_NAME_LENGTH),
    ]);

    expect($result->success)->toBeTrue()
        ->and($gateway->calls)->toBe(['updateProfile']);
});

it('refuses a display name longer than an account holds, and says how long one can be', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;

    $result = makeAccountService($gateway)->updateProfile(7, [
        'display_name' => str_repeat('n', AccountService::MAX_DISPLAY_NAME_LENGTH + 1),
        'first_name' => 'Jane',
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('display_name_too_long')
        ->and($result->message)->toContain((string) AccountService::MAX_DISPLAY_NAME_LENGTH)
        ->and($gateway->calls)->toBe([]);
});

it('counts a display name in characters, not in bytes', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;

    // An Arabic letter is two bytes.
    $result = makeAccountService($gateway)->updateProfile(7, [
        'display_name' => str_repeat('م', AccountService::MAX_DISPLAY_NAME_LENGTH),
    ]);

    expect($result->success)->toBeTrue();
});

it('refuses a display name that fits as it was typed and not as the account would hold it', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;
    $gateway->displayNameAsKept = str_repeat('n', AccountService::MAX_DISPLAY_NAME_LENGTH + 1);

    $result = makeAccountService($gateway)->updateProfile(7, ['display_name' => 'Tom & Jerry']);

    expect($result->success)->toBeFalse()
        ->and($result->code)->toBe('display_name_too_long')
        // The person is within the number: telling them the number would send them in a circle.
        ->and($result->message)->not->toContain((string) AccountService::MAX_DISPLAY_NAME_LENGTH)
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

it('refuses to change a profile to an address longer than an account holds', function () {
    $gateway = new FakeAuthGateway();
    $gateway->currentUserId = 7;

    $result = makeAccountService($gateway)->updateProfile(7, [
        'display_name' => 'Jane Doe',
        'email' => str_repeat('a', 60) . '@' . str_repeat('b', 28) . '.example.com',
    ]);

    expect($result->code)->toBe('email_too_long')
        ->and($gateway->calls)->toBe([]);
});
