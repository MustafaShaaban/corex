<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * A registered Corex add-on: its slug (plugin directory), the plugin file to toggle, a
 * human label, the optional feature flag toggled alongside the plugin, and the add-on
 * slugs it depends on. A pure value object — the registry holds these, the manager reasons
 * over them.
 */
final class Addon
{
    /**
     * @param list<string> $requires add-on slugs this one depends on
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $pluginFile,
        public readonly string $label,
        public readonly ?string $flag = null,
        public readonly array $requires = [],
    ) {
    }

    public function hasFlag(): bool
    {
        return $this->flag !== null && $this->flag !== '';
    }
}
