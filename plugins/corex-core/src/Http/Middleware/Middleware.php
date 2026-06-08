<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * One unit of the request pipeline. It either returns a Response (short-circuit)
 * or calls $next to pass control inward to the rest of the chain (spec FR-001).
 */
interface Middleware
{
    /**
     * @param callable(Request): Response $next
     */
    public function process(Request $request, callable $next): Response;
}
