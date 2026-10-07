<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * One value in an exported table, with what kind of value it is.
 *
 * A writer needs the kind: a spreadsheet sorts a date as a date only if it is written as one, and
 * a phone number that is all digits must stay text.
 */
final readonly class ExportCell
{
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const DATETIME = 'datetime';

    private const DATETIME_AS_TEXT = 'Y-m-d H:i';

    private function __construct(public string $type, public string|int|float|DateTimeImmutable $value)
    {
    }

    public static function text(string $value): self
    {
        return new self(self::TEXT, $value);
    }

    public static function number(int|float $value): self
    {
        return new self(self::NUMBER, $value);
    }

    /**
     * @param DateTimeImmutable $value Already in the timezone it should be read in.
     */
    public static function dateTime(DateTimeImmutable $value): self
    {
        return new self(self::DATETIME, $value);
    }

    /**
     * The value as a person reads it, for a format that has no types of its own.
     */
    public function asText(): string
    {
        return $this->value instanceof DateTimeImmutable
            ? $this->value->format(self::DATETIME_AS_TEXT)
            : (string) $this->value;
    }
}
