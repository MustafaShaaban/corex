<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Routes;

defined('ABSPATH') || exit;

/**
 * Reads WordPress's registered REST routes into {@see RouteDescriptor}s (spec 046, US2),
 * filtered to the requested namespaces (Corex + the app). The parsing (`fromRoutes`) is
 * pure + unit-tested; `read()` is the thin runtime boundary that calls `rest_get_server()`.
 */
final class RoutesReader
{
    /**
     * @param list<string> $namespaces first segments to keep, e.g. ['corex', 'app']
     *
     * @return list<RouteDescriptor>
     */
    public function read(array $namespaces): array
    {
        if (! function_exists('rest_get_server')) {
            return [];
        }

        return $this->fromRoutes(rest_get_server()->get_routes(), $namespaces);
    }

    /**
     * @param array<string,mixed> $routes     WP's get_routes() structure (path => handlers)
     * @param list<string>        $namespaces first segments to keep
     *
     * @return list<RouteDescriptor>
     */
    public function fromRoutes(array $routes, array $namespaces): array
    {
        $descriptors = [];

        foreach ($routes as $path => $handlers) {
            if (! is_string($path) || ! $this->inNamespaces($path, $namespaces)) {
                continue;
            }

            [$namespace, $relative] = $this->split($path);

            if ($relative === '') {
                continue; // the bare namespace index route
            }

            $methods = [];
            $guarded = false;

            foreach ((array) $handlers as $handler) {
                if (! is_array($handler)) {
                    continue;
                }
                foreach (array_keys((array) ($handler['methods'] ?? [])) as $method) {
                    $methods[(string) $method] = true;
                }
                $permission = $handler['permission_callback'] ?? null;
                if ($permission !== null && $permission !== '__return_true') {
                    $guarded = true;
                }
            }

            $descriptors[] = new RouteDescriptor($namespace, $relative, implode(', ', array_keys($methods)), $guarded);
        }

        return $descriptors;
    }

    /**
     * @param list<string> $namespaces
     */
    private function inNamespaces(string $path, array $namespaces): bool
    {
        $first = explode('/', trim($path, '/'))[0] ?? '';

        return in_array($first, $namespaces, true);
    }

    /**
     * Split `/corex/v1/data/x` into the `corex/v1` namespace and the `/data/x` remainder.
     *
     * @return array{0:string,1:string}
     */
    private function split(string $path): array
    {
        $parts = explode('/', trim($path, '/'));

        $namespace = implode('/', array_slice($parts, 0, 2));
        $relative  = implode('/', array_slice($parts, 2));

        return [$namespace, $relative === '' ? '' : '/' . $relative];
    }
}
