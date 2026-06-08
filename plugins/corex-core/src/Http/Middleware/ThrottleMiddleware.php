<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * Rate-limits requests by key: rejects once the per-key count reaches the limit
 * within the window, otherwise increments and passes. State lives in a WP
 * transient, so the count resets when the window expires (spec FR-009).
 */
final class ThrottleMiddleware implements Middleware
{
    public function __construct(
        private readonly int $limit,
        private readonly int $window,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $key = 'corex_throttle_' . md5($request->throttleKey);
        $count = (int) get_transient($key);

        if ($count >= $this->limit) {
            return Response::reject('Too many requests. Please try again later.', 429);
        }

        set_transient($key, $count + 1, $this->window);

        return $next($request);
    }
}
