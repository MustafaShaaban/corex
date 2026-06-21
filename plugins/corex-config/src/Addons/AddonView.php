<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Foundation\AddonStatus;

defined('ABSPATH') || exit;

/**
 * One add-on's render model: the add-on plus its computed state (installed, active, flag
 * on) and, when a toggle would break a dependency, the reason it is blocked (null when the
 * add-on is freely togglable). A pure value object the screen renders.
 *
 * Spec 060 / M6: it also resolves to one truthful {@see AddonStatus} so the Add-ons screen
 * shows a single honest state and offers enable/disable only for installed add-ons. The
 * `dependencyMissing`/`wooMissing`/`proRequired` inputs are additive (default false) so
 * existing constructions keep working.
 */
final class AddonView
{
    public function __construct(
        public readonly Addon $addon,
        public readonly bool $installed,
        public readonly bool $active,
        public readonly bool $flagOn,
        public readonly ?string $blockedReason = null,
        public readonly bool $dependencyMissing = false,
        public readonly bool $wooMissing = false,
        public readonly bool $proRequired = false,
    ) {
    }

    public function isBlocked(): bool
    {
        return $this->blockedReason !== null;
    }

    /**
     * The single truthful display state, in the canonical order: pro_required →
     * not_installed → inactive → dependency_missing → feature_off → woocommerce_missing →
     * active.
     */
    public function status(): AddonStatus
    {
        if ($this->proRequired) {
            return AddonStatus::ProRequired;
        }

        if (! $this->installed) {
            return AddonStatus::NotInstalled;
        }

        if (! $this->active) {
            return AddonStatus::Inactive;
        }

        if ($this->dependencyMissing) {
            return AddonStatus::DependencyMissing;
        }

        if ($this->addon->hasFlag() && ! $this->flagOn) {
            return AddonStatus::FeatureOff;
        }

        if ($this->wooMissing) {
            return AddonStatus::WoocommerceMissing;
        }

        return AddonStatus::Active;
    }
}
