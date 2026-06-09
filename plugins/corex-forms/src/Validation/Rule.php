<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Validation;

defined('ABSPATH') || exit;

/**
 * A single, stateless validation rule. Pure: it returns an i18n message *key* on
 * failure (e.g. `email`, `max`) or null on pass, and never touches WordPress.
 */
interface Rule
{
    /**
     * @param array<int,string>   $params    rule parameters (e.g. ['80'] for max:80)
     * @param array<string,mixed> $allValues the full payload, for cross-field rules
     *
     * @return string|null an i18n message key on failure, null on pass
     */
    public function validate(mixed $value, array $params, array $allValues): ?string;
}
