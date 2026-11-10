<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Fails when the value is absent, an empty/whitespace-only string, or an empty array.
 */
final class Required implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        return self::missing($value) ? 'required' : null;
    }

    /** Whether there is no answer: absent, blank, or an empty list. Shared with `required_if`. */
    public static function missing(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }
}
