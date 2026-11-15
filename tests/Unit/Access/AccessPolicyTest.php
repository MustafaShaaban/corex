<?php

/**
 * Unit tests for CoreX access-change lockout prevention (spec 068: FR-087–FR-089).
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\InvalidAccessInput;

beforeEach(function () {
    $this->policy = new AccessPolicy(CorexAbilityCatalog::defaults());
});

it('allows a non-critical ability grant', function () {
    $preview = $this->policy->preview(
        actorId: 7,
        changes: [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW],
        affectedUserIds: [12, 14],
        fullAccessAdminIds: [7],
    );

    expect($preview->allowed)->toBeTrue()
        ->and($preview->blockers)->toBe([])
        ->and($preview->changes)->toBe([CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW]);
});

it('blocks removing the current actors critical access', function () {
    $preview = $this->policy->preview(
        actorId: 7,
        changes: [CorexAbility::MANAGE_ACCESS => AccessPolicy::EFFECT_DENY],
        affectedUserIds: [7],
        fullAccessAdminIds: [7, 9],
        actorAdministersSite: true,
    );

    expect($preview->allowed)->toBeFalse()
        ->and(array_column($preview->blockers, 'code'))->toContain('self_lockout');
});

it('blocks removing critical access from every full-access administrator', function () {
    $preview = $this->policy->preview(
        actorId: 4,
        changes: [CorexAbility::MANAGE_ACCESS => AccessPolicy::EFFECT_DENY],
        affectedUserIds: [7, 9],
        fullAccessAdminIds: [7, 9],
    );

    expect($preview->allowed)->toBeFalse()
        ->and(array_column($preview->blockers, 'code'))->toContain('last_full_access_admin');
});

it('allows a critical change when another full-access administrator remains', function () {
    $preview = $this->policy->preview(
        actorId: 4,
        changes: [CorexAbility::MANAGE_ACCESS => AccessPolicy::EFFECT_DENY],
        affectedUserIds: [7],
        fullAccessAdminIds: [7, 9],
    );

    expect($preview->allowed)->toBeTrue();
});

it('blocks edits to code-locked ability definitions', function () {
    $preview = $this->policy->preview(
        actorId: 4,
        changes: [CorexAbility::MANAGE_ADMIN => AccessPolicy::EFFECT_ALLOW],
        affectedUserIds: [12],
        fullAccessAdminIds: [4],
    );

    expect($preview->allowed)->toBeFalse()
        ->and(array_column($preview->blockers, 'code'))->toContain('ability_locked');
});

it('refuses a change it cannot read, and says which part of it', function (array $changes, string $refusal) {
    expect(fn () => $this->policy->preview(1, $changes, [], [1]))->toThrow(function (InvalidAccessInput $refused) use ($refusal) {
        expect($refused->parameter)->toBe('changes')
            ->and($refused->refusal)->toBe($refusal);
    });
})->with([
    'an ability CoreX does not have' => [['corex_unknown' => AccessPolicy::EFFECT_ALLOW], InvalidAccessInput::ABILITY_UNKNOWN],
    // PHP keeps the key "123" of an array as the integer 123, and a list's keys are integers.
    'a key that is a number'         => [['123' => AccessPolicy::EFFECT_ALLOW], InvalidAccessInput::ABILITY_UNKNOWN],
    'a list where a map was meant'   => [[AccessPolicy::EFFECT_ALLOW], InvalidAccessInput::ABILITY_UNKNOWN],
    'an effect CoreX does not have'  => [[CorexAbility::MANAGE_FORMS => 'maybe'], InvalidAccessInput::EFFECT_UNSUPPORTED],
]);

it('refuses a change with nobody behind it as a mistake in the code that called it', function () {
    $preview = fn () => $this->policy->preview(0, [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW], [], [1]);

    // Not an `InvalidAccessInput`: a route answers that one as the caller's mistake, and a change
    // with no actor is a mistake in CoreX.
    expect($preview)->toThrow(function (InvalidArgumentException $thrown) {
        expect($thrown)->not->toBeInstanceOf(InvalidAccessInput::class);
    });
});

it('refuses a Deny on a role that administers the site, and allows anything else there', function () {
    $deny = $this->policy->preview(
        actorId: 7,
        changes: [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY],
        affectedUserIds: [7, 9],
        fullAccessAdminIds: [7, 9],
        administersSite: true,
        actorAdministersSite: true,
    );
    $inherit = $this->policy->preview(
        actorId: 7,
        changes: [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_INHERIT],
        affectedUserIds: [7, 9],
        fullAccessAdminIds: [7, 9],
        administersSite: true,
        actorAdministersSite: true,
    );

    expect($deny->allowed)->toBeFalse()
        ->and($deny->blockers)->toBe([['code' => 'site_administrator_role', 'ability' => CorexAbility::MANAGE_EMAIL]])
        ->and($inherit->allowed)->toBeTrue();
});

it('lets a Deny on an ordinary role through', function () {
    $preview = $this->policy->preview(
        actorId: 7,
        changes: [CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY],
        affectedUserIds: [12],
        fullAccessAdminIds: [7],
    );

    expect($preview->allowed)->toBeTrue();
});

/*
 * DECISIONS #428: what only a site administrator may change. The person making the change is
 * actor 4; the role holds 12 and 14.
 */
it('refuses to let someone who does not administer the site allow or set to inherit a Critical ability', function (string $effect) {
    $preview = $this->policy->preview(
        actorId: 4,
        changes: [CorexAbility::MANAGE_ACCESS => $effect],
        affectedUserIds: [12, 14],
        fullAccessAdminIds: [1],
    );

    expect($preview->allowed)->toBeFalse()
        ->and($preview->blockers)->toBe([['code' => AccessPolicy::CRITICAL_ALLOW, 'ability' => CorexAbility::MANAGE_ACCESS]]);
})->with([AccessPolicy::EFFECT_ALLOW, AccessPolicy::EFFECT_INHERIT]);

it('lets someone who does not administer the site deny a Critical ability on another role', function () {
    $preview = $this->policy->preview(
        actorId: 4,
        changes: [CorexAbility::RUN_DANGEROUS_ACTIONS => AccessPolicy::EFFECT_DENY],
        affectedUserIds: [12, 14],
        fullAccessAdminIds: [1],
    );

    expect($preview->allowed)->toBeTrue();
});

it('refuses every change to a role the person making it holds, unless they administer the site', function () {
    $changes = [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW, CorexAbility::MANAGE_EMAIL => AccessPolicy::EFFECT_DENY];

    $manager = $this->policy->preview(actorId: 12, changes: $changes, affectedUserIds: [12, 14], fullAccessAdminIds: [1]);
    $admin   = $this->policy->preview(actorId: 12, changes: $changes, affectedUserIds: [12, 14], fullAccessAdminIds: [1], actorAdministersSite: true);

    expect($manager->blockers)->toBe([
        ['code' => AccessPolicy::OWN_ACCESS, 'ability' => CorexAbility::MANAGE_FORMS],
        ['code' => AccessPolicy::OWN_ACCESS, 'ability' => CorexAbility::MANAGE_EMAIL],
    ])->and($admin->allowed)->toBeTrue();
});

it('lets a site administrator allow a Critical ability for a role', function () {
    $preview = $this->policy->preview(
        actorId: 4,
        changes: [CorexAbility::MANAGE_OPERATIONS => AccessPolicy::EFFECT_ALLOW],
        affectedUserIds: [12],
        fullAccessAdminIds: [4],
        actorAdministersSite: true,
    );

    expect($preview->allowed)->toBeTrue();
});
