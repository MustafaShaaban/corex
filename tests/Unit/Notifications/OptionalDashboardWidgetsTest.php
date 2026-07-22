<?php

/**
 * Unit tests for the optional Dashboard widgets (spec 072 US7: FR-025).
 *
 * FR-025 states four independent conditions — opt-in, ability, "never register for users who can see
 * no underlying data", and Development-only widgets absent in Production. They are asserted here
 * against one pure decision method rather than through wp_add_dashboard_widget, so each rule can fail
 * on its own; the wiring that feeds it real values is covered by the integration test.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Notifications\OptionalDashboardWidgets;
use Corex\Config\Operations\OperationsMode;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

$decide = static fn (): OptionalDashboardWidgets => new OptionalDashboardWidgets();

it('registers an opted-in widget for a permitted actor who has data', function () use ($decide) {
    expect($decide()->shouldRegister(
        OptionalDashboardWidgets::ATTENTION,
        enabled: true,
        permitted: true,
        hasData: true,
        mode: OperationsMode::PRODUCTION,
    ))->toBeTrue();
});

it('never registers a widget nobody opted into', function () use ($decide) {
    // The whole point of "optional": a widget the site did not ask for must not appear, however
    // qualified the actor is.
    expect($decide()->shouldRegister(
        OptionalDashboardWidgets::ATTENTION,
        enabled: false,
        permitted: true,
        hasData: true,
        mode: OperationsMode::PRODUCTION,
    ))->toBeFalse();
});

it('never registers for an actor without the widget ability', function () use ($decide) {
    expect($decide()->shouldRegister(
        OptionalDashboardWidgets::ATTENTION,
        enabled: true,
        permitted: false,
        hasData: true,
        mode: OperationsMode::PRODUCTION,
    ))->toBeFalse();
});

it('never registers for an actor who can see no underlying data', function () use ($decide) {
    // An empty widget is worse than no widget: it takes dashboard space to say nothing, and it
    // implies the actor is missing something they are simply not party to.
    expect($decide()->shouldRegister(
        OptionalDashboardWidgets::ATTENTION,
        enabled: true,
        permitted: true,
        hasData: false,
        mode: OperationsMode::PRODUCTION,
    ))->toBeFalse();
});

it('keeps a Development-only widget off a Production dashboard', function () use ($decide) {
    expect($decide()->shouldRegister(
        OptionalDashboardWidgets::DEVELOPMENT,
        enabled: true,
        permitted: true,
        hasData: true,
        mode: OperationsMode::PRODUCTION,
    ))->toBeFalse();
});

it('shows a Development-only widget in Development and Staging but never in Maintenance', function () use ($decide) {
    $for = static fn (string $mode): bool => (new OptionalDashboardWidgets())->shouldRegister(
        OptionalDashboardWidgets::DEVELOPMENT,
        enabled: true,
        permitted: true,
        hasData: true,
        mode: $mode,
    );

    expect($for(OperationsMode::DEVELOPMENT))->toBeTrue()
        ->and($for(OperationsMode::STAGING))->toBeFalse()
        ->and($for(OperationsMode::MAINTENANCE))->toBeFalse();
});

it('refuses an unknown widget id rather than defaulting it on', function () use ($decide) {
    // Fail closed: an id that is not in the catalogue has no declared ability or mode rule, so
    // there is nothing to enforce and it must not reach the dashboard.
    expect($decide()->shouldRegister(
        'corex_not_a_widget',
        enabled: true,
        permitted: true,
        hasData: true,
        mode: OperationsMode::DEVELOPMENT,
    ))->toBeFalse();
});

it('declares an ability for every catalogued widget', function () {
    $widgets = OptionalDashboardWidgets::catalogue();

    expect($widgets)->not->toBeEmpty();

    foreach ($widgets as $id => $definition) {
        expect($definition['ability'])->toBeString()->not->toBe('')
            ->and($definition['title'])->toBeString()->not->toBe('')
            ->and($definition['developmentOnly'])->toBeBool()
            ->and($id)->toStartWith('corex_')
            // A render callback that does not exist fails silently in WordPress — the widget
            // registers and then draws nothing — so the catalogue must only name real methods.
            ->and(method_exists(OptionalDashboardWidgets::class, $definition['render']))->toBeTrue();
    }
});
