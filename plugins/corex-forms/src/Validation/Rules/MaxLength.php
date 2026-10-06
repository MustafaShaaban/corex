<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

/**
 * Upper bound on the number of characters, whatever the characters are: `01016999700` is eleven
 * characters, not a number over a thousand million. An empty value passes (see `required`).
 *
 * Fails with the same key as {@see Max}, so the two share one message.
 */
final class MaxLength implements Rule
{
    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_strlen((string) $value) > (int) ($params[0] ?? 0) ? 'max' : null;
    }
}
