<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Fails when a non-empty value is not a syntactically valid email address.
 * An empty value passes here — emptiness is the `required` rule's concern.
 */
final class Email implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false ? 'email' : null;
    }
}
