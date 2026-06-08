<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * Reduces a request's input to the declared expected shape: only the named keys
 * survive, each run through its sanitizer; the handler never sees unexpected or
 * unsanitized input (spec FR-010). Transforms, never rejects.
 */
final class SanitizeMiddleware implements Middleware
{
    /**
     * @param array<string, callable|string> $shape key => sanitizer (callable or WP function name)
     */
    public function __construct(private readonly array $shape)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $clean = [];

        foreach ($this->shape as $key => $sanitizer) {
            if (array_key_exists($key, $request->input)) {
                $clean[$key] = is_callable($sanitizer) ? $sanitizer($request->input[$key]) : $request->input[$key];
            }
        }

        return $next($request->withInput($clean));
    }
}
