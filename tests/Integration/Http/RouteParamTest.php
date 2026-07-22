<?php

/**
 * Unit tests for route-captured parameter reads (spec 070).
 *
 * The defect these cover: WP_REST_Request::get_parameter_order() resolves JSON body params and
 * the query string *before* URL params, so a payload carrying its own `id` silently shadows the
 * id the route matched on. Saving an email-template draft posted the stored version's id
 * alongside the fields, the controller read that instead of the template id in the path, found
 * no such template, and answered 404 — for a template that plainly existed.
 *
 * @package Corex\Tests\Unit\Http
 */

declare(strict_types=1);

use Corex\Http\RouteParam;

function routeRequest(array $urlParams, array $body = [], array $query = []): WP_REST_Request
{
    $request = new WP_REST_Request('POST', '/corex/v1/email-studio/templates/3859/draft');
    $request->set_url_params($urlParams);
    $request->set_query_params($query);

    if ($body !== []) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode($body));
    }

    return $request;
}

it('reads the id the route matched on', function () {
    expect(RouteParam::int(routeRequest(['id' => '3859'])))->toBe(3859);
});

it('is not shadowed by an id in the request body', function () {
    // The exact shape that produced the 404: the editor posted the version record's own id.
    $request = routeRequest(['id' => '3859'], ['id' => 3860, 'subject' => 'Hello']);

    expect($request->get_param('id'))->toBe(3860)   // what the controller used to read
        ->and(RouteParam::int($request))->toBe(3859); // what it routes to
});

it('is not shadowed by an id in the query string', function () {
    $request = routeRequest(['id' => '3859'], [], ['id' => '99']);

    expect(RouteParam::int($request))->toBe(3859);
});

it('reads a named string parameter such as an attempt uuid', function () {
    $request = routeRequest(['attempt' => 'ab12cd34-0000-4000-8000-000000000000']);

    expect(RouteParam::string($request, 'attempt'))
        ->toBe('ab12cd34-0000-4000-8000-000000000000');
});

it('falls back to empty values when the route captured nothing', function () {
    $request = routeRequest([], ['id' => 77]);

    expect(RouteParam::int($request))->toBe(0)
        ->and(RouteParam::string($request, 'attempt'))->toBe('');
});
