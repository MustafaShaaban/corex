<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * A registered Corex add-on: its slug (plugin directory), the plugin file to toggle, a
 * human label, the optional feature flag toggled alongside the plugin, and the add-on slugs
 * it depends on — plus a rich manifest (spec 044): a summary/description, what it registers
 * (`provides`), the config keys it needs to be useful (`needsKeys`), and a docs link. A pure
 * value object — the registry holds these, the manager reasons over them. The manifest fields
 * default to empty so existing registrations keep working (additive).
 */
final class Addon
{
    /**
     * @param list<string> $requires add-on slugs this one depends on
     * @param list<string> $provides human labels of what enabling it registers (blocks/CPTs/routes/settings)
     * @param list<string> $needsKeys Config dot-keys that must be set for the add-on to be configured
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $pluginFile,
        public readonly string $label,
        public readonly ?string $flag = null,
        public readonly array $requires = [],
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly array $provides = [],
        public readonly array $needsKeys = [],
        public readonly string $docsUrl = '',
        public readonly ?AddonTier $tier = null,
    ) {
    }

    public function hasFlag(): bool
    {
        return $this->flag !== null && $this->flag !== '';
    }

    public function needsConfiguration(): bool
    {
        return $this->needsKeys !== [];
    }

    /**
     * The config keys this add-on needs that are still empty.
     *
     * @param callable(string):string $value reads a setting value by dot-key
     *
     * @return list<string>
     */
    public function missingKeys(callable $value): array
    {
        return array_values(array_filter(
            $this->needsKeys,
            static fn (string $key): bool => trim((string) $value($key)) === '',
        ));
    }
}
