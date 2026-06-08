<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Facades;

defined('ABSPATH') || exit;

use Corex\Boot;

/**
 * Bounded global container accessor (spec FR-008a).
 *
 * For framework-boundary use only — hook callbacks and WP-CLI/cron bootstrap where
 * constructor injection cannot reach. Application services and controllers MUST
 * receive their dependencies via constructor injection, never through this facade.
 */
final class Corex
{
    /**
     * @param array<string, mixed> $parameters
     */
    public static function make(string $id, array $parameters = []): mixed
    {
        return Boot::app()->container()->make($id, $parameters);
    }
}
