<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Lower bound on the number of characters, whatever the characters are: `12` is two characters,
 * not a number that clears a minimum of three. An empty value passes (see `required`).
 *
 * Fails with the same key as {@see Min}, so the two share one message.
 */
final class MinLength implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strlen((string) $value) < (int) ($params[0] ?? 0) ? 'min' : null;
    }
}
