<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Casts;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Casts a value to its PHP type on hydrate and to a storable scalar on persist.
 * Pure. Supported types: int, bool, string, decimal/float, array/json, datetime.
 * A malformed JSON value hydrates to an empty array (non-fatal).
 */
final class Caster
{
    public function toPhp(mixed $value, string $type): mixed
    {
        return match ($type) {
            'int', 'integer'   => (int) $value,
            'bool', 'boolean'  => (bool) $value,
            'string'           => (string) $value,
            'decimal', 'float' => (float) $value,
            'array', 'json'    => $this->decodeArray($value),
            'datetime'         => $this->toDate($value),
            default            => $value,
        };
    }

    public function toStore(mixed $value, string $type): mixed
    {
        return match ($type) {
            'array', 'json'   => is_string($value) ? $value : (string) json_encode($value),
            'bool', 'boolean' => $value ? 1 : 0,
            'datetime'        => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value,
            'int', 'integer'  => (int) $value,
            'decimal', 'float' => (float) $value,
            default           => (string) $value,
        };
    }

    /**
     * @return array<mixed>
     */
    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function toDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
