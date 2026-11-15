<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * One page of the Settings screen (spec 119, US-SET-01): its address key, its name, the registry
 * sections it saves, and the add-on it belongs to, when it belongs to one.
 */
final readonly class SettingsPage
{
    /**
     * @param list<string> $sections registry section keys, in the order they are drawn
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public array $sections,
        public ?SettingsAddon $addon = null,
    ) {
    }

    public function holds(string $sectionKey): bool
    {
        return in_array($sectionKey, $this->sections, true);
    }
}
