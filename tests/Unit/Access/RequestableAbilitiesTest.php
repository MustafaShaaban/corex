<?php

/**
 * Which abilities and areas an access request may ask for (spec 112, User Story 1, FR-001): none
 * that is locked or critical, and none that implies one.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Access\CorexAbilityCatalog;
use Corex\Access\RequestableAbilities;

function requestableAbilities(): RequestableAbilities
{
    return new RequestableAbilities(CorexAbilityCatalog::defaults());
}

it('answers for every ability CoreX has', function (string $ability, bool $requestable) {
    expect(requestableAbilities()->isRequestable($ability))->toBe($requestable);
})->with([
    'access CoreX admin (locked)'         => [CorexAbility::MANAGE_ADMIN, false],
    'manage access (critical)'            => [CorexAbility::MANAGE_ACCESS, false],
    'manage operations (critical)'        => [CorexAbility::MANAGE_OPERATIONS, false],
    'install releases (critical)'         => [CorexAbility::MANAGE_RELEASES, false],
    'run dangerous actions (critical)'    => [CorexAbility::RUN_DANGEROUS_ACTIONS, false],
    'manage forms'                        => [CorexAbility::MANAGE_FORMS, true],
    'manage submissions'                  => [CorexAbility::MANAGE_SUBMISSIONS, true],
    'manage data (dangerous)'             => [CorexAbility::MANAGE_DATA, true],
    'manage data models (dangerous)'      => [CorexAbility::MANAGE_DATA_MODELS, true],
    'export personal data'                => [CorexAbility::EXPORT_PERSONAL_DATA, true],
    'manage Email Studio'                 => [CorexAbility::MANAGE_EMAIL, true],
    'manage Blog Pro'                     => [CorexAbility::MANAGE_BLOG, true],
    'manage notifications'                => [CorexAbility::MANAGE_NOTIFICATIONS, true],
    'manage setup (dangerous)'            => [CorexAbility::MANAGE_SETUP, true],
    'manage settings'                     => [CorexAbility::MANAGE_SETTINGS, true],
]);

it('refuses an ability that implies one only an administrator grants', function () {
    $catalog = CorexAbilityCatalog::defaults();
    $catalog->register(new CorexAbility(
        'corex_test_wraps_access',
        'Wraps access',
        'A normal ability that implies a critical one.',
        CorexAbility::GROUP_FORMS,
        CorexAbility::RISK_NORMAL,
        false,
        [CorexAbility::MANAGE_ACCESS],
        [],
        [],
    ));

    expect((new RequestableAbilities($catalog))->isRequestable('corex_test_wraps_access'))->toBeFalse();
});

it('refuses an area that holds an ability only an administrator grants', function (string $area, bool $requestable) {
    expect(requestableAbilities()->isAreaRequestable($area))->toBe($requestable);
})->with([
    'CoreX admin'          => [CorexAbility::GROUP_ADMIN, false],
    'operations and security' => [CorexAbility::GROUP_OPERATIONS, false],
    'forms and flows'      => [CorexAbility::GROUP_FORMS, true],
    'data'                 => [CorexAbility::GROUP_DATA, true],
    'Email Studio'         => [CorexAbility::GROUP_EMAIL, true],
]);
