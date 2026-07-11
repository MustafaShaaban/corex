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

it('rejects unknown abilities and effects', function () {
    expect(fn () => $this->policy->preview(1, ['corex_unknown' => AccessPolicy::EFFECT_ALLOW], [], [1]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->policy->preview(1, [CorexAbility::MANAGE_FORMS => 'maybe'], [], [1]))
        ->toThrow(InvalidArgumentException::class);
});
