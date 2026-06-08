<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Fields;

defined('ABSPATH') || exit;

/**
 * Custom-field access, independent of ACF. Concrete drivers resolve fields through
 * ACF (when present) or native post meta (when absent) behind this interface, so
 * calling code is identical either way (spec FR-008).
 */
interface FieldDriver
{
    /**
     * Read a field; return $default when it is absent (FR-011).
     */
    public function get(int $entityId, string $key, mixed $default = null): mixed;

    public function set(int $entityId, string $key, mixed $value): void;
}
