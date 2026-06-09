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
        if ($value === null) {
            return 'required';
        }

        if (is_string($value) && trim($value) === '') {
            return 'required';
        }

        if (is_array($value) && $value === []) {
            return 'required';
        }

        return null;
    }
}
