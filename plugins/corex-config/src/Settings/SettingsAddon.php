<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * The add-on a Settings page belongs to (spec 119, US-SET-07): its plugin file, the slug its card
 * on the Add-ons screen is found by, and its name as a person reads it.
 */
final readonly class SettingsAddon
{
    public function __construct(
        public string $pluginFile,
        public string $slug,
        public string $name,
    ) {
    }

    /** Where this add-on's card is on the Add-ons screen. */
    public function cardAnchor(): string
    {
        return 'corex-addon-' . $this->slug;
    }
}
