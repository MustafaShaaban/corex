<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Docs;

use Corex\Cli\Routes\RouteDescriptor;

defined('ABSPATH') || exit;

/**
 * Emits an OpenAPI 3 document for the Corex REST API (spec 046, US3) from the route
 * descriptors + the spec-043 envelope schema + the auth scheme. Pure: descriptors in, an
 * OpenAPI array out (the command JSON-encodes it). Carries no secret.
 */
final class ApiDocsGenerator
{
    /**
     * @param list<RouteDescriptor> $routes
     *
     * @return array<string,mixed>
     */
    public function generate(array $routes, string $title, string $version): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $path = '/' . trim($route->namespace . $route->path, '/');

            foreach ($this->methods($route->methods) as $method) {
                $paths[$path][$method] = [
                    'summary'   => $route->namespace . $route->path,
                    'security'  => $route->guarded ? [['corexNonce' => []]] : [],
                    'responses' => [
                        '200' => [
                            'description' => 'Corex response envelope',
                            'content'     => [
                                'application/json' => ['schema' => ['$ref' => '#/components/schemas/Envelope']],
                            ],
                        ],
                    ],
                ];
            }
        }

        return [
            'openapi'    => '3.0.3',
            'info'       => ['title' => $title, 'version' => $version],
            'paths'      => $paths,
            'components' => [
                'schemas'         => ['Envelope' => $this->envelopeSchema()],
                'securitySchemes' => [
                    'corexNonce'  => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-WP-Nonce'],
                    'appPassword' => ['type' => 'http', 'scheme' => 'basic'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function methods(string $methods): array
    {
        return array_values(array_filter(array_map(
            static fn (string $method): string => strtolower(trim($method)),
            explode(',', $methods),
        )));
    }

    /**
     * @return array<string,mixed>
     */
    private function envelopeSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'ok'      => ['type' => 'boolean'],
                'message' => ['type' => 'string'],
                'data'    => ['type' => 'object'],
                'code'    => ['type' => 'string'],
                'errors'  => ['type' => 'object'],
                'details' => ['type' => 'object'],
            ],
            'required'   => ['ok'],
        ];
    }
}
