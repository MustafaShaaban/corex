<?php

/**
 * Corrective Spec 060 coverage that every discovered CoreX screen uses the shared
 * visual shell and renders a visible permission-denied state.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('applies the shared page shell to every current CoreX admin renderer', function (string $file, string $section) {
    $source = (string) file_get_contents(ThemeContract::root() . '/' . $file);

    expect($source)->toContain('AdminPage')
        ->and($source)->toContain('->open(')
        ->and($source)->toContain("'{$section}',")
        ->and($source)->toContain("->permissionDenied('{$section}'")
        ->and($source)->toContain('->close()');
})->with([
    'overview' => ['plugins/corex-config/src/Settings/AdminDashboard.php', 'overview'],
    'add-ons' => ['plugins/corex-config/src/Addons/AddonsScreen.php', 'addons'],
    'data' => ['plugins/corex-config/src/Data/DataAdminScreen.php', 'data'],
    'insights and readiness' => ['plugins/corex-config/src/Insights/InsightsScreen.php', 'insights'],
    'setup wizard' => ['addons/corex-kit-company/src/SetupWizardScreen.php', 'setup'],
    'declarative option pages' => ['plugins/corex-config/src/Options/OptionPageScreen.php', 'option-page'],
]);

it('exposes Overview and Settings as distinct CoreX menu destinations', function () {
    $source = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/src/Settings/AdminDashboard.php',
    );

    expect($source)->toContain("'COREX FRAMEWORK'")
        ->and($source)->toContain("'corex-settings-config'")
        ->and($source)->toContain('renderSettings')
        ->and($source)->toContain('CoreX Overview')
        ->and($source)->toContain('CoreX Settings');
});

it('renders the Overview stat-card and Setup progress component contracts', function () {
    $root = ThemeContract::root();
    $status = (string) file_get_contents($root . '/plugins/corex-config/src/Dashboard/SiteStatusCardRenderer.php');
    $setup = (string) file_get_contents($root . '/addons/corex-kit-company/src/SetupWizardScreen.php');

    expect($status)->toContain('corex-stat-card')
        ->and($status)->toContain('corex-site-status')
        ->and($setup)->toContain('corex-wizard__steps')
        ->and($setup)->toContain('corex-wizard__kits');
});

it('renders explicit empty states for add-ons and setup collections', function () {
    $root = ThemeContract::root();
    $addons = (string) file_get_contents($root . '/plugins/corex-config/src/Addons/AddonsScreen.php');
    $setup = (string) file_get_contents($root . '/addons/corex-kit-company/src/SetupWizardScreen.php');

    expect($addons)->toContain("->state(\n                'empty'")
        ->and($setup)->toContain("->state(\n                'empty'");
});

it('keeps declarative option-page writes on the shared AdminGuard', function () {
    $source = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/src/Options/OptionPageScreen.php',
    );

    expect($source)->toContain('->verifiedPost(')
        ->and($source)->toContain("'corex_optionpage_nonce'")
        ->and($source)->not->toContain('check_admin_referer(')
        ->and($source)->not->toContain('current_user_can(')
        ->and($source)->not->toContain('new SettingsForm(');
});

it('preserves an existing declarative option-page secret on an empty submit', function () {
    $source = (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/src/Options/OptionPageScreen.php',
    );

    expect($source)->toContain("\$field['type'] === 'password'")
        ->and($source)->toContain("\$sanitized === ''")
        ->and($source)->toContain('continue;');
});
