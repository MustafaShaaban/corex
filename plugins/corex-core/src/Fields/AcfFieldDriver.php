<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Fields;

defined('ABSPATH') || exit;

/**
 * The ACF field driver: reads/writes through ACF's own API so complex field types
 * and return-format coercion are handled. Selected by FieldResolver only when ACF
 * is present (spec FR-009); never referenced when ACF is absent (FR-010).
 */
final class AcfFieldDriver implements FieldDriver
{
    public function get(int $entityId, string $key, mixed $default = null): mixed
    {
        $value = get_field($key, $entityId);

        return $value === null ? $default : $value;
    }

    public function set(int $entityId, string $key, mixed $value): void
    {
        update_field($key, $value, $entityId);
    }
}
