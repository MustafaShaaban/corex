<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * A stored value as a person reads it.
 *
 * An answer with several choices (a multi-select, a checkbox group) is stored as a list, and a
 * list of plain values is exactly a comma-separated sentence. The Data screen printed it as
 * JSON, `["brand-identity","motion-graphics"]`, where an operator expected two services; the
 * export had the rule and the screen did not, and a client site carried the difference as a
 * patch.
 *
 * A keyed or nested value keeps its JSON on purpose: joining `['source' => 'newsletter']` would
 * print `newsletter` and throw away the half that says what it is.
 */
final class ReadableValue
{
    public static function of(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value) && array_is_list($value) && self::allScalar($value)) {
            return implode(', ', array_map('strval', $value));
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @param list<mixed> $items */
    private static function allScalar(array $items): bool
    {
        return array_filter($items, static fn (mixed $item): bool => ! is_scalar($item)) === [];
    }
}
