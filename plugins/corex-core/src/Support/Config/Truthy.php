<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Gives every feature-flag boundary one deliberately narrow truthiness rule (spec 100 FR-025).
 */
final class Truthy
{
    private const TRUTHY = ['1', 'true', 'on', 'yes'];

    public static function of(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), self::TRUTHY, true);
        }

        return false;
    }
}
