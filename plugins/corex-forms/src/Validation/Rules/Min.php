<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Lower bound on a number: the value must be ≥ N. An empty value passes (see `required` for
 * emptiness).
 *
 * A form reaches this rule only for a field that is a number — the `number` or `rating` type, or
 * any field declaring `numeric`. `SchemaResolver` turns `min:N` on every other field into
 * {@see MinLength}, so `12` in a name is two characters, not the number twelve.
 *
 * An answer that is not numeric is measured by its length. Declare `numeric` to refuse it.
 */
final class Min implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $limit = (int) ($params[0] ?? 0);

        if (is_numeric($value)) {
            return ((float) $value) < $limit ? 'min' : null;
        }

        return mb_strlen((string) $value) < $limit ? 'min' : null;
    }
}
