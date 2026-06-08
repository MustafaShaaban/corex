<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

use Corex\Support\BootLogger;
use Throwable;

/**
 * Runs a request through an ordered middleware chain wrapped around a handler
 * (onion model): the first middleware is outermost, the handler innermost. A
 * middleware short-circuits by returning a Response; a throw anywhere is caught
 * and converted to a rejection — fail closed, never an open pass (spec FR-002,
 * FR-006).
 */
final class Pipeline
{
    public function __construct(private readonly BootLogger $logger)
    {
    }

    /**
     * @param callable(Request): Response $handler
     */
    public function run(Request $request, callable $handler, Middleware ...$middleware): Response
    {
        $next = $handler;

        foreach (array_reverse($middleware) as $current) {
            $inner = $next;
            $next = static fn (Request $r): Response => $current->process($r, $inner);
        }

        try {
            return $next($request);
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Middleware pipeline error: %s', $e->getMessage()));

            return Response::reject('Request could not be processed.', 500);
        }
    }
}
