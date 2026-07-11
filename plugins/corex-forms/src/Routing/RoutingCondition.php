<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Routing;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * One field/operator/value condition in an ordered routing plan.
 */
final readonly class RoutingCondition
{
    private const OPERATORS = ['equals', 'not_equals', 'contains', 'in', 'exists', 'empty'];

    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value = null,
    ) {
        if ($field === '' || ! in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException('Routing condition is invalid.');
        }
    }

    /** @param array<string,mixed> $values */
    public function matches(array $values): bool
    {
        $actual = $values[$this->field] ?? null;

        return match ($this->operator) {
            'equals'     => $actual === $this->value,
            'not_equals' => $actual !== $this->value,
            'contains'   => $this->contains($actual),
            'in'         => is_array($this->value) && in_array($actual, $this->value, true),
            'exists'     => array_key_exists($this->field, $values),
            'empty'      => $actual === null || $actual === '' || $actual === [],
        };
    }

    private function contains(mixed $actual): bool
    {
        if (is_array($actual)) {
            return in_array($this->value, $actual, true);
        }

        return is_string($actual) && is_string($this->value) && str_contains($actual, $this->value);
    }
}
