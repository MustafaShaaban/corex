<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Keyed read access over the layered configuration sources (spec FR-011, FR-012).
 */
interface ConfigInterface
{
    /**
     * Resolve a dot-notation key. The first source that holds it wins; otherwise
     * the caller default is returned. Never throws on a missing key.
     */
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;
}
