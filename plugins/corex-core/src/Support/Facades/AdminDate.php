<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Facades;

defined('ABSPATH') || exit;

use Corex\Boot;
use Corex\Support\DateTime\AdminDateTime;
use Corex\Support\DateTime\Formatted;
use DateTimeImmutable;

/**
 * Static accessor for admin date presentation (framework boundary; see FR-008a).
 *
 * The same bounded-accessor pattern as {@see Config}: it **resolves from the container** rather
 * than constructing, so there is still one shared instance and a test can still replace the
 * binding. It exists because server-rendered screens format dates inside `foreach` loops in view
 * code that has no constructor to inject into — threading the presenter through nine renderers to
 * deliver one call per row would add a dependency each of them holds and uses once.
 *
 * Where a constructor exists, inject {@see AdminDateTime} instead. This is for view code.
 */
final class AdminDate
{
    /**
     * @param int|string|DateTimeImmutable|null $value  Any stored or transported timestamp.
     * @param string                            $absent What to say when there is no instant.
     */
    public static function full(
        int|string|DateTimeImmutable|null $value,
        string $absent = '',
    ): Formatted {
        return self::presenter()->format($value, AdminDateTime::FULL, $absent);
    }

    public static function date(
        int|string|DateTimeImmutable|null $value,
        string $absent = '',
    ): Formatted {
        return self::presenter()->format($value, AdminDateTime::DATE, $absent);
    }

    public static function time(
        int|string|DateTimeImmutable|null $value,
        string $absent = '',
    ): Formatted {
        return self::presenter()->format($value, AdminDateTime::TIME, $absent);
    }

    public static function relative(
        int|string|DateTimeImmutable|null $value,
        string $absent = '',
    ): Formatted {
        return self::presenter()->format($value, AdminDateTime::RELATIVE, $absent);
    }

    /** Seconds included. Diagnostics only — never an ordinary operator view (FR-004). */
    public static function exact(
        int|string|DateTimeImmutable|null $value,
        string $absent = '',
    ): Formatted {
        return self::presenter()->format($value, AdminDateTime::EXACT, $absent);
    }

    /**
     * The payload the browser needs to produce identical output.
     *
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        return self::presenter()->clientConfig();
    }

    private static function presenter(): AdminDateTime
    {
        return Boot::app()->container()->make(AdminDateTime::class);
    }
}
