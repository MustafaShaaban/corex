<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation\Rules;

defined('ABSPATH') || exit;

use Corex\Forms\Validation\Rule;

final class Pattern implements Rule
{
    private const MAX_PATTERN_BYTES = 512;
    private const MAX_VALUE_BYTES = 10000;

    public function validate(mixed $value, array $params, array $allValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $pattern = (string) ($params[0] ?? '');
        if ($pattern === '' || strlen($pattern) > self::MAX_PATTERN_BYTES || strlen((string) $value) > self::MAX_VALUE_BYTES) {
            return 'pattern';
        }

        $expression = '~(*LIMIT_MATCH=100000)(*LIMIT_DEPTH=1000)(?:'
            . str_replace('~', '\\~', $pattern)
            . ')~uD';

        set_error_handler(static fn (): bool => true, E_WARNING);
        try {
            $matches = preg_match($expression, (string) $value) === 1;
        } finally {
            restore_error_handler();
        }

        return $matches ? null : 'pattern';
    }
}
