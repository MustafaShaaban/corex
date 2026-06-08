<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Fields;

defined('ABSPATH') || exit;

/**
 * The default field driver: native WordPress post meta. Used whenever ACF is not
 * installed, so the framework runs fully without ACF (spec FR-010).
 */
final class MetaFieldDriver implements FieldDriver
{
    public function get(int $entityId, string $key, mixed $default = null): mixed
    {
        $value = get_post_meta($entityId, $key, true);

        // get_post_meta returns '' for an absent key; treat that as "use the default".
        return $value === '' ? $default : $value;
    }

    public function set(int $entityId, string $key, mixed $value): void
    {
        update_post_meta($entityId, $key, $value);
    }
}
