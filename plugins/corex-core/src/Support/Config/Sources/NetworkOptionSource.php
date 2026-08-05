<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config\Sources;

defined('ABSPATH') || exit;

use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Support\Config\SettingRegistry;
use Corex\Support\Config\SettingScope;
use Corex\Support\Config\Source;

/**
 * Shares the three-part network-option guard while leaving precedence to the concrete source
 * position (spec 100 FR-020–FR-024).
 */
abstract class NetworkOptionSource implements Source
{
    private const PREFIX = 'corex_network_';

    private const SENTINEL = "\0corex_network_option_unset\0";

    public function __construct(
        private readonly SettingRegistry $registry,
        private readonly MultisiteContext $multisite,
        private readonly NetworkContext $network,
    ) {
    }

    final public function has(string $key): bool
    {
        if (! $this->multisite->enabled()) {
            return false;
        }

        if ($this->registry->scopeOf($key) !== $this->claimedScope()) {
            return false;
        }

        return get_network_option($this->network->id(), $this->optionName($key), self::SENTINEL)
            !== self::SENTINEL;
    }

    final public function get(string $key): mixed
    {
        return get_network_option($this->network->id(), $this->optionName($key));
    }

    abstract protected function claimedScope(): SettingScope;

    private function optionName(string $key): string
    {
        return self::PREFIX . str_replace('.', '_', $key);
    }
}
