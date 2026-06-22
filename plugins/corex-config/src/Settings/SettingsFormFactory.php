<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/** Creates a form for a runtime-declared settings section registry. */
final class SettingsFormFactory
{
    public function make(FieldSections $sections): SettingsForm
    {
        return new SettingsForm($sections);
    }
}
