<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Provisioning;

defined('ABSPATH') || exit;

/**
 * The default {@see KitProvisioner} when no kit framework is active: a Null Object. It reports no applicable
 * kits and applies nothing, so corex-config can always resolve the seam and degrade gracefully — no add-on
 * prompt shows and the dashboard card renders its empty state (Principle IX). The kit framework overrides this
 * binding with a real implementation.
 */
final class NullKitProvisioner implements KitProvisioner
{
    public function applicableKits(): array
    {
        return [];
    }

    public function isApplicable(string $kit): bool
    {
        return false;
    }

    public function kitForModule(string $addonSlug): ?string
    {
        return null;
    }

    public function preview(string $kit): ApplyPreview
    {
        return new ApplyPreview($kit, [], null, [], []);
    }

    public function apply(string $kit): ApplyOutcome
    {
        return new ApplyOutcome([], [], [], null);
    }
}
