<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

/**
 * The single truthful display state of a CoreX add-on, surfaced in the admin
 * (Spec 060 / M6). Resolved by {@see AddonStatusResolver}. `Active` is the only
 * usable state; only installed states can be toggled from the admin (install is
 * developer/CLI/deployment work, never an admin action).
 */
enum AddonStatus: string
{
    case NotInstalled = 'not_installed';
    case Inactive = 'inactive';
    case FeatureOff = 'feature_off';
    case Active = 'active';
    case DependencyMissing = 'dependency_missing';
    case WoocommerceMissing = 'woocommerce_missing';
    case ProRequired = 'pro_required';

    /** Only an active, fully-satisfied add-on is usable. */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /** An add-on is "installed" for admin purposes unless it is absent or a Pro placeholder. */
    public function isInstalled(): bool
    {
        return $this !== self::NotInstalled && $this !== self::ProRequired;
    }

    /** Enable/disable is offered only for installed add-ons — never not-installed or Pro. */
    public function canToggle(): bool
    {
        return $this->isInstalled();
    }
}
