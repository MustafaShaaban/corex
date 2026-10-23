<?php

/**
 * Unit tests for the one map from CoreX screen to the ability it needs (spec 117, FR-001), and for
 * the screen access modes and the recorded check built on it.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Corex\Access\AbilityCheck;
use Corex\Access\CorexAbility;
use Corex\Access\ScreenAbilities;
use Corex\Admin\AdminPage;
use Corex\Config\Access\ScreenAccess;

/** Makes `current_user_can()` answer from a list of held abilities. */
function holding(string ...$abilities): void
{
    Functions\when('current_user_can')->alias(static fn (string $cap): bool => in_array($cap, $abilities, true));
}

it('names the ability each screen needs, by page slug and by section', function (string $slug, string $section, string $ability) {
    expect(ScreenAbilities::abilityFor($slug))->toBe($ability)
        ->and(ScreenAbilities::abilityFor($section))->toBe($ability);
})->with([
    ['corex-settings', 'overview', CorexAbility::MANAGE_ADMIN],
    ['corex-addons', 'addons', CorexAbility::MANAGE_ADMIN],
    ['corex-insights', 'insights', CorexAbility::MANAGE_ADMIN],
    ['corex-forms', 'forms', CorexAbility::MANAGE_FORMS],
    ['corex-email-studio', 'email', CorexAbility::MANAGE_EMAIL],
    ['corex-settings-config', 'settings', CorexAbility::MANAGE_SETTINGS],
    ['corex-operations-security', 'operations-security', CorexAbility::MANAGE_OPERATIONS],
    ['corex-access', 'access', CorexAbility::MANAGE_ACCESS],
    ['corex-blog-pro', 'blog-pro', CorexAbility::MANAGE_BLOG],
    ['corex-setup', 'setup', CorexAbility::MANAGE_SETUP],
    ['corex-submissions', 'submissions', CorexAbility::MANAGE_SUBMISSIONS],
    ['corex-notifications', 'notifications', CorexAbility::MANAGE_NOTIFICATIONS],
    ['corex-releases', 'releases', CorexAbility::MANAGE_RELEASES],
]);

it('opens exactly Overview, Settings, Operations & Security and Insights read-only', function () {
    $readOnly = array_keys(array_filter(ScreenAbilities::all(), static fn (array $screen): bool => $screen['readOnly']));

    expect($readOnly)->toEqualCanonicalizing(['corex-settings', 'corex-settings-config', 'corex-operations-security', 'corex-insights']);
});

it('makes the denied page ask for the same ability the screen checks', function () {
    foreach (ScreenAbilities::all() as $slug => $screen) {
        expect(AdminPage::requestAbilityFor($slug))->toBe($screen['ability'])
            ->and(AdminPage::requestAbilityFor($screen['section']))->toBe($screen['ability']);
    }
});

it('gives a screen fully to the holder of its ability', function () {
    holding(CorexAbility::MANAGE_SETTINGS, CorexAbility::VIEW_SCREENS);
    $access = new ScreenAccess();

    expect($access->mode('corex-settings-config'))->toBe(ScreenAccess::MANAGE)
        ->and($access->menuCapability('corex-settings-config'))->toBe(CorexAbility::MANAGE_SETTINGS);
});

it('opens a read-only screen without its controls for the view ability alone', function () {
    holding(CorexAbility::VIEW_SCREENS);
    $access = new ScreenAccess();

    expect($access->mode('corex-settings-config'))->toBe(ScreenAccess::VIEW)
        ->and($access->readOnly('corex-settings-config'))->toBeTrue()
        ->and($access->menuCapability('corex-settings-config'))->toBe(CorexAbility::VIEW_SCREENS)
        ->and($access->mode('corex-forms'))->toBe(ScreenAccess::NONE)
        ->and($access->menuCapability('corex-forms'))->toBe(CorexAbility::MANAGE_FORMS);
});

it('refuses a screen to someone holding neither ability', function () {
    holding();

    expect((new ScreenAccess())->opens('corex-insights'))->toBeFalse();
});

it('records a refused check under the screen it belongs to, for a signed-in person', function () {
    holding();
    Functions\when('is_user_logged_in')->justReturn(true);
    Actions\expectDone('corex_access_refused')->once()->with('action', 'corex-email-studio', CorexAbility::MANAGE_EMAIL);

    expect((new AbilityCheck())->allows(CorexAbility::MANAGE_EMAIL, 'corex-email-studio'))->toBeFalse();
});

it('records nothing when the check passes, or for nobody signed in', function () {
    Actions\expectDone('corex_access_refused')->never();

    holding(CorexAbility::MANAGE_EMAIL);
    expect((new AbilityCheck())->allows(CorexAbility::MANAGE_EMAIL, 'corex-email-studio'))->toBeTrue();

    holding();
    Functions\when('is_user_logged_in')->justReturn(false);
    expect((new AbilityCheck())->allows(CorexAbility::MANAGE_EMAIL, 'corex-email-studio'))->toBeFalse();
});

it('lets the view ability read only the screens that open read-only', function () {
    holding(CorexAbility::VIEW_SCREENS);
    Functions\when('is_user_logged_in')->justReturn(true);
    $check = new AbilityCheck();

    expect($check->allowsReading(CorexAbility::MANAGE_ADMIN, 'corex-insights'))->toBeTrue()
        ->and($check->allows(CorexAbility::MANAGE_ADMIN, 'corex-insights'))->toBeFalse()
        ->and($check->allowsReading(CorexAbility::MANAGE_EMAIL, 'corex-email-studio'))->toBeFalse();
});
