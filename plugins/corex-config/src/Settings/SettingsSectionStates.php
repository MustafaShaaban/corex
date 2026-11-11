<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Multisite\PluginActivationInspector;

/**
 * The state of each Settings section (spec 060 M6, spec 119 US-SET-07).
 *
 * A section on a page that belongs to an add-on follows it: Hidden when the add-on's plugin is not
 * there, Disabled when it is off. Media, Captcha and Guides belong to add-ons; Guides used to stay
 * editable with Corex Guides off, saving an address nothing read. A captcha provider that needs a
 * key it does not have is ConfigurationNeeded, read from the value in force.
 */
final class SettingsSectionStates
{
    public function __construct(
        private readonly SettingsPages $pages,
        private readonly PluginActivationInspector $plugins,
        private readonly ProviderSetup $providers,
    ) {
    }

    public function of(string $sectionKey): ?SettingsSectionState
    {
        $addon = $this->pages->holding($sectionKey)?->addon;
        if ($addon === null) {
            return null;
        }

        $state = $this->forAddon($addon);
        if ($state !== SettingsSectionState::Normal) {
            return $state;
        }

        return $sectionKey === 'captcha' && $this->providers->captchaNeedsSetup()
            ? SettingsSectionState::ConfigurationNeeded
            : SettingsSectionState::Normal;
    }

    public function forPage(SettingsPage $page): SettingsSectionState
    {
        return $page->addon === null ? SettingsSectionState::Normal : $this->forAddon($page->addon);
    }

    /** Whether a save may write this section: its add-on is there and on (spec 114, FR-028). */
    public function editable(string $sectionKey): bool
    {
        $state = $this->of($sectionKey);

        return $state !== SettingsSectionState::Hidden && $state !== SettingsSectionState::Disabled;
    }

    private function forAddon(SettingsAddon $addon): SettingsSectionState
    {
        if (! file_exists(WP_PLUGIN_DIR . '/' . $addon->pluginFile)) {
            return SettingsSectionState::Hidden;
        }

        return $this->plugins->isActive($addon->pluginFile) ? SettingsSectionState::Normal : SettingsSectionState::Disabled;
    }
}
