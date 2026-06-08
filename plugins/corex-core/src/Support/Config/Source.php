<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * A single configuration layer. `get()` is only called when `has()` is true,
 * so a stored null is distinguishable from an absent key.
 */
interface Source
{
    public function has(string $key): bool;

    public function get(string $key): mixed;
}
