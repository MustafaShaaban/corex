<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Upper bound on a number: the value must be ≤ N. An empty value passes (see `required` for
 * emptiness).
 *
 * A form reaches this rule only for a field that is a number — the `number` or `rating` type, or
 * any field declaring `numeric`. `SchemaResolver` turns `max:N` on every other field into
 * {@see MaxLength}, so `2025` in a message is counted, not compared.
 *
 * An answer that is not numeric is measured by its length. Declare `numeric` to refuse it.
 */
final class Max implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $limit = (int) ($params[0] ?? 0);

        if (is_numeric($value)) {
            return ((float) $value) > $limit ? 'max' : null;
        }

        return mb_strlen((string) $value) > $limit ? 'max' : null;
    }
}
