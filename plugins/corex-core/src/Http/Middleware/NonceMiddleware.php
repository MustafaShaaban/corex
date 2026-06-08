<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * Rejects a state-changing (non-GET) request without a valid WP nonce; read-only
 * requests pass. Which methods require a nonce is configurable (spec FR-007).
 */
final class NonceMiddleware implements Middleware
{
    /**
     * @param list<string> $protectedMethods
     */
    public function __construct(
        private readonly array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'],
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (! in_array(strtoupper($request->method), $this->protectedMethods, true)) {
            return $next($request);
        }

        if (wp_verify_nonce($request->nonce, $request->nonceAction) === false) {
            return Response::reject('Invalid or missing security token.', 403);
        }

        return $next($request);
    }
}
