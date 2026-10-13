<?php

/**
 * What the access service says when it refuses a request for what the request says.
 *
 * A caller has to be able to tell that refusal from a failure of CoreX's own: the REST route
 * answers the first as the caller's mistake and names the parameter, and must not do that for the
 * second.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AccessPolicy;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\InvalidAccessInput;
use Corex\Access\RoleAbilityStore;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Access\AccessService;
use Corex\Mail\RoutedMailer;

beforeEach(function () {
    Functions\when('__')->returnArg();

    // A call to any of these fails the test: a refused request stores nothing, writes no audit
    // entry and tells nobody.
    $catalog = CorexAbilityCatalog::defaults();
    $this->service = new AccessService(
        catalog: $catalog,
        policy: new AccessPolicy($catalog),
        roleAbilities: Mockery::mock(RoleAbilityStore::class),
        requests: Mockery::mock(AccessRequestStore::class),
        users: Mockery::mock(AccessUserDirectory::class),
        activity: new ActivityService(Mockery::mock(ActivityRepository::class)),
        notifications: Mockery::mock(RoutedMailer::class),
    );

    $this->now = new DateTimeImmutable('2026-10-13T10:00:00+00:00');
});

it('refuses a request for what it says, naming the parameter and why', function (?string $ability, ?string $area, string $reason, string $parameter, string $refusal) {
    $ask = fn () => $this->service->requestAccess(12, $ability, $area, $reason, $this->now, $this->now->modify('+7 days'));

    expect($ask)->toThrow(function (InvalidAccessInput $refused) use ($parameter, $refusal) {
        expect($refused->parameter)->toBe($parameter)
            ->and($refused->refusal)->toBe($refusal);
    });
})->with([
    'no reason'                      => [CorexAbility::MANAGE_FORMS, null, '', 'reason', InvalidAccessInput::REASON_REQUIRED],
    'a reason of spaces'             => [CorexAbility::MANAGE_FORMS, null, "  \n ", 'reason', InvalidAccessInput::REASON_REQUIRED],
    'a reason one character over'    => [CorexAbility::MANAGE_FORMS, null, str_repeat('a', AccessService::MAX_REASON_LENGTH + 1), 'reason', InvalidAccessInput::REASON_TOO_LONG],
    'neither an ability nor an area' => [null, null, 'I maintain the forms.', 'ability', InvalidAccessInput::TARGET_REQUIRED],
    'an ability and an area'         => [CorexAbility::MANAGE_FORMS, 'forms-flows', 'I maintain the forms.', 'area', InvalidAccessInput::TARGET_AMBIGUOUS],
    'an ability CoreX does not have' => ['corex_no_such_ability', null, 'I maintain the forms.', 'ability', InvalidAccessInput::ABILITY_UNKNOWN],
    'an area CoreX does not have'    => [null, 'forms', 'I maintain the forms.', 'area', InvalidAccessInput::AREA_UNKNOWN],
]);

it('refuses a request nobody made, or one already run out, as a mistake in the code that called it', function (int $requesterId, string $expires) {
    $ask = fn () => $this->service->requestAccess($requesterId, CorexAbility::MANAGE_FORMS, null, 'I maintain the forms.', $this->now, $this->now->modify($expires));

    // Not an `InvalidAccessInput`: neither is something a person sent, and a route must not
    // answer either as a mistake in what was sent.
    expect($ask)->toThrow(function (InvalidArgumentException $thrown) {
        expect($thrown)->not->toBeInstanceOf(InvalidAccessInput::class);
    });
})->with([
    'no requester'    => [0, '+7 days'],
    'already run out' => [12, '-1 second'],
]);

it('refuses a role key that is not one, for a preview and for a hash', function (string $role) {
    $refusedForItsRole = function (InvalidAccessInput $refused) {
        expect($refused->parameter)->toBe('role')
            ->and($refused->refusal)->toBe(InvalidAccessInput::ROLE_INVALID);
    };

    expect(fn () => $this->service->previewRoleChanges(7, $role, []))->toThrow($refusedForItsRole)
        ->and(fn () => $this->service->roleChangeHash($role, []))->toThrow($refusedForItsRole);
})->with([
    'empty'    => [''],
    'capitals' => ['Editor'],
]);
