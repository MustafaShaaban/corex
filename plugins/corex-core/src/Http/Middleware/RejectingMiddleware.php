<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * Always rejects — the resolver substitutes this for an unknown middleware name so
 * a typo fails closed instead of silently dropping protection (spec FR-015).
 */
final class RejectingMiddleware implements Middleware
{
    public function __construct(private readonly string $reason = 'Forbidden')
    {
    }

    public function process(Request $request, callable $next): Response
    {
        return Response::reject($this->reason, 403);
    }
}
