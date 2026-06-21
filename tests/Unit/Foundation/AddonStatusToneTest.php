<?php

/**
 * Spec 060 / M6 — each AddonStatus carries a semantic badge tone so the admin can show
 * the state with meaning that is not by color alone (the label) and a consistent tone
 * mapped to the --corex-admin-* success/warning/error/neutral roles.
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Foundation\AddonStatus;

it('maps the usable state to a success tone', function () {
    expect(AddonStatus::Active->tone())->toBe('success');
});

it('maps installed-but-off states to a warning tone', function () {
    expect(AddonStatus::Inactive->tone())->toBe('warning')
        ->and(AddonStatus::FeatureOff->tone())->toBe('warning');
});

it('maps unmet-requirement states to a danger tone', function () {
    expect(AddonStatus::DependencyMissing->tone())->toBe('danger')
        ->and(AddonStatus::WoocommerceMissing->tone())->toBe('danger');
});

it('maps absent / future states to a neutral tone', function () {
    expect(AddonStatus::NotInstalled->tone())->toBe('neutral')
        ->and(AddonStatus::ProRequired->tone())->toBe('neutral');
});

it('gives every state a tone', function () {
    foreach (AddonStatus::cases() as $status) {
        expect($status->tone())->toBeIn(['success', 'warning', 'danger', 'neutral']);
    }
});
