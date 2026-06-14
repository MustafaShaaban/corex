<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Routes;

defined('ABSPATH') || exit;

/**
 * Formats {@see RouteDescriptor}s into readable lines grouped by namespace (spec 046, US2) —
 * the body of `wp corex routes:list`. Pure: takes descriptors, returns lines.
 */
final class RouteList
{
    /**
     * @param list<RouteDescriptor> $routes
     *
     * @return list<string>
     */
    public function lines(array $routes): array
    {
        if ($routes === []) {
            return [];
        }

        $byNamespace = [];
        foreach ($routes as $route) {
            $byNamespace[$route->namespace][] = $route;
        }

        $lines = [];
        foreach ($byNamespace as $namespace => $group) {
            $lines[] = $namespace;
            foreach ($group as $route) {
                $lines[] = sprintf(
                    '  [%s] %s — %s',
                    $route->methods,
                    $route->path,
                    $route->guarded ? 'guarded' : 'public',
                );
            }
        }

        return $lines;
    }
}
