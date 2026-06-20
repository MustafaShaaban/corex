<?php

/**
 * Spec 060 / M6 US2 — settings sections reflect the runtime add-on state.
 *
 * A section's display derives purely from its add-on's AddonStatus plus whether it is
 * configured: not installed → hidden; active+configured → normal; active+unconfigured
 * → configuration needed; every other (non-usable) state → disabled. No section may
 * present usable fields for a non-active add-on.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Config\Settings\SettingsSectionState;
use Corex\Foundation\AddonStatus;

it('hides a section whose add-on is not installed', function () {
    expect(SettingsSectionState::forStatus(AddonStatus::NotInstalled, false))
        ->toBe(SettingsSectionState::Hidden);
});

it('disables a section for any installed-but-not-usable state', function () {
    foreach ([
        AddonStatus::Inactive,
        AddonStatus::FeatureOff,
        AddonStatus::DependencyMissing,
        AddonStatus::WoocommerceMissing,
        AddonStatus::ProRequired,
    ] as $status) {
        expect(SettingsSectionState::forStatus($status, false))
            ->toBe(SettingsSectionState::Disabled, $status->value);
    }
});

it('asks for configuration when active but unconfigured', function () {
    expect(SettingsSectionState::forStatus(AddonStatus::Active, false))
        ->toBe(SettingsSectionState::ConfigurationNeeded);
});

it('shows normal settings when active and configured', function () {
    expect(SettingsSectionState::forStatus(AddonStatus::Active, true))
        ->toBe(SettingsSectionState::Normal);
});

it('never shows usable fields for a non-active add-on', function () {
    foreach (AddonStatus::cases() as $status) {
        $section = SettingsSectionState::forStatus($status, true);

        if ($status !== AddonStatus::Active) {
            expect($section->showsUsableFields())->toBeFalse($status->value);
        }
    }

    expect(SettingsSectionState::forStatus(AddonStatus::Active, true)->showsUsableFields())->toBeTrue();
});
