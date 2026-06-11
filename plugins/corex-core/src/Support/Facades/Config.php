<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Facades;

defined('ABSPATH') || exit;

use Corex\Boot;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;

/**
 * Static accessor for layered configuration (framework boundary; see FR-008a).
 */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Boot::app()->container()->make(ConfigInterface::class)->get($key, $default);
    }

    public static function has(string $key): bool
    {
        return Boot::app()->container()->make(ConfigInterface::class)->has($key);
    }

    /**
     * Whether a `features.*` flag is enabled (env/option/default layered).
     */
    public static function enabled(string $flag, bool $default = false): bool
    {
        return Boot::app()->container()->make(FeatureFlags::class)->enabled($flag, $default);
    }
}
