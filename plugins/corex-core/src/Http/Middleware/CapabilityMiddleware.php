<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * Rejects a request whose current user lacks the required capability (spec FR-008).
 */
final class CapabilityMiddleware implements Middleware
{
    public function __construct(private readonly string $capability)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (! current_user_can($this->capability)) {
            return Response::reject('You do not have permission to perform this action.', 403);
        }

        return $next($request);
    }
}
