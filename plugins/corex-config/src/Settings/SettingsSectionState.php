<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Foundation\AddonStatus;

/**
 * The display state of a settings section, derived purely from its add-on's
 * {@see AddonStatus} and whether it is configured (Spec 060 / M6 US2). This keeps
 * every settings section honest: a section never presents usable fields for an add-on
 * that is not active, and never shows settings for an add-on that is not installed.
 */
enum SettingsSectionState: string
{
    case Hidden = 'hidden';
    case Disabled = 'disabled';
    case ConfigurationNeeded = 'configuration_needed';
    case Normal = 'normal';

    public static function forStatus(AddonStatus $status, bool $configured): self
    {
        if ($status === AddonStatus::NotInstalled) {
            return self::Hidden;
        }

        if ($status === AddonStatus::Active) {
            return $configured ? self::Normal : self::ConfigurationNeeded;
        }

        // Installed but not usable (inactive / feature off / dependency or WooCommerce
        // missing / Pro): show a disabled state, never active fields.
        return self::Disabled;
    }

    /** Only a fully active, configured section presents usable settings fields. */
    public function showsUsableFields(): bool
    {
        return $this === self::Normal;
    }
}
