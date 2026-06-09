<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Fails when a non-empty value is not numeric. An empty value passes (see
 * `required` for emptiness).
 */
final class Numeric implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? null : 'numeric';
    }
}
