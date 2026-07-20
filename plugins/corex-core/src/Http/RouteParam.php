<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Read a parameter the *route* declared, never one the caller supplied.
 *
 * WP_REST_Request::get_param() searches by WP_REST_Request::get_parameter_order(), which puts
 * JSON body params and the query string ahead of URL params. So for a route like
 * `/templates/(?P<id>\d+)/draft`, a request body carrying its own `id` key silently shadows the
 * id in the path, and the handler operates on whatever the client named instead of what it
 * routed to. That is how saving an email-template draft came to 404: the editor posted the
 * stored version's `id` alongside the fields, and the controller looked that up as a template.
 *
 * Reading straight from get_url_params() cannot be shadowed, so route identity always comes from
 * the path. Use this for anything the route pattern captures; get_param() remains correct for
 * genuine payload and query values.
 */
final class RouteParam
{
    /**
     * A route-captured integer id, defaulting to 0 when the route did not capture one.
     */
    public static function int(WP_REST_Request $request, string $name = 'id'): int
    {
        return absint(self::raw($request, $name) ?? 0);
    }

    /**
     * A route-captured string, defaulting to '' when the route did not capture one.
     *
     * Returned as-is rather than sanitized: a URL param only exists because the route's own
     * pattern matched it, so its shape is already constrained by the regex the route declared
     * (`(?P<attempt>[0-9a-f-]+)`, say). Callers that interpolate the value anywhere still owe
     * it the context-correct escape at the point of output.
     */
    public static function string(WP_REST_Request $request, string $name): string
    {
        return (string) (self::raw($request, $name) ?? '');
    }

    private static function raw(WP_REST_Request $request, string $name): mixed
    {
        return $request->get_url_params()[$name] ?? null;
    }
}
