<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Lower bound. For numeric values the value must be ≥ N; for strings the length
 * must be ≥ N. An empty value passes (see `required` for emptiness).
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
