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
        // WordPress takes one level of slashes off a meta value on its way in: handed over as
        // it is, text is stored without its backslashes. A value that is not text is not changed.
        update_post_meta($entityId, $key, wp_slash($value));
    }
}
