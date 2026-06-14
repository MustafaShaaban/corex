<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Routes;

defined('ABSPATH') || exit;

/**
 * One registered REST route as pure data (spec 046, US2): its namespace, path, the methods
 * it accepts, and whether it is guarded (a non-public permission callback). Enumerated by
 * the routes reader at the runtime boundary; powers `routes:list` and `api:docs`.
 */
final class RouteDescriptor
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $path,
        public readonly string $methods,
        public readonly bool $guarded,
    ) {
    }
}
