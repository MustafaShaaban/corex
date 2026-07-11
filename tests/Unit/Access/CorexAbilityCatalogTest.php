<?php

/**
 * Unit tests for grouped CoreX access abilities (spec 068: FR-084–FR-087, FR-095).
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;

it('defines every approved product ability group with unique corex keys', function () {
    $catalog = CorexAbilityCatalog::defaults();
    $groups  = array_keys($catalog->grouped());
    $keys    = array_map(static fn (CorexAbility $ability): string => $ability->key, $catalog->all());

    expect($groups)->toBe([
        CorexAbility::GROUP_ADMIN,
        CorexAbility::GROUP_FORMS,
        CorexAbility::GROUP_SUBMISSIONS,
        CorexAbility::GROUP_DATA,
        CorexAbility::GROUP_DATA_MODELS,
        CorexAbility::GROUP_EMAIL,
        CorexAbility::GROUP_BLOG,
        CorexAbility::GROUP_OPERATIONS,
        CorexAbility::GROUP_SETUP,
        CorexAbility::GROUP_SETTINGS,
    ])->and(count($keys))->toBe(count(array_unique($keys)));

    foreach ($keys as $key) {
        expect($key)->toStartWith('corex_');
    }
});
it('marks critical access abilities and resolves declared implications', function () {
    $catalog = CorexAbilityCatalog::defaults();
    $admin   = $catalog->find(CorexAbility::MANAGE_ADMIN);

    expect($admin)->not->toBeNull()
        ->and($admin->risk)->toBe(CorexAbility::RISK_CRITICAL)
        ->and($admin->locked)->toBeTrue()
        ->and($catalog->expanded([CorexAbility::MANAGE_ADMIN]))
        ->toContain(CorexAbility::MANAGE_FORMS, CorexAbility::MANAGE_ACCESS, CorexAbility::MANAGE_SETTINGS);
});

it('rejects duplicate and malformed ability definitions', function () {
    $catalog = new CorexAbilityCatalog();
    $ability = new CorexAbility(
        key: 'corex_manage_example',
        label: 'Manage example',
        description: 'Manage the example area.',
        group: CorexAbility::GROUP_SETTINGS,
        risk: CorexAbility::RISK_NORMAL,
        locked: false,
        implies: [],
        screenSlugs: ['corex-example'],
        actionKeys: ['example.save'],
    );

    $catalog->register($ability);

    expect(fn () => $catalog->register($ability))->toThrow(DomainException::class)
        ->and(fn () => new CorexAbility(
            key: 'manage_example',
            label: 'Manage example',
            description: 'Manage the example area.',
            group: CorexAbility::GROUP_SETTINGS,
            risk: CorexAbility::RISK_NORMAL,
            locked: false,
            implies: [],
            screenSlugs: [],
            actionKeys: [],
        ))->toThrow(InvalidArgumentException::class);
});
