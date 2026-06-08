<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Models;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * A read-only value object describing one entity's shape (spec FR-001–FR-003).
 *
 * Holds typed attributes only — no query logic, no data-source calls, no
 * persistence (writes go through the Repository, which returns a fresh Model).
 * A subclass declares its post type, field map, optional relations, and casts.
 */
abstract class Model
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(protected readonly array $attributes)
    {
    }

    abstract public static function postType(): string;

    /**
     * Logical field name => meta/ACF key.
     *
     * @return array<string, string>
     */
    abstract public static function fields(): array;

    /**
     * Logical name => ['type' => 'belongsTo', 'model' => class-string, 'foreignKey' => string].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function relations(): array
    {
        return [];
    }

    /**
     * Attribute => 'int'|'bool'|'string'|'array'|DateTimeImmutable::class.
     *
     * @return array<string, string>
     */
    public static function casts(): array
    {
        return [];
    }

    public function id(): int
    {
        return (int) ($this->attributes['id'] ?? 0);
    }

    public function get(string $attribute, mixed $default = null): mixed
    {
        if (! array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->cast($attribute, $this->attributes[$attribute]);
    }

    /**
     * Return a NEW Model with one attribute added/replaced (immutable copy).
     * Used to attach an eager-loaded relation without mutating the original.
     */
    public function withAttribute(string $attribute, mixed $value): static
    {
        return new static(array_merge($this->attributes, [$attribute => $value]));
    }

    private function cast(string $attribute, mixed $value): mixed
    {
        $type = static::casts()[$attribute] ?? null;

        if ($type === null || $value === null) {
            return $value;
        }

        return match ($type) {
            'int'                    => (int) $value,
            'bool'                   => (bool) $value,
            'string'                 => (string) $value,
            'array'                  => (array) $value,
            DateTimeImmutable::class => $value instanceof DateTimeImmutable ? $value : new DateTimeImmutable((string) $value),
            default                  => $value,
        };
    }
}
