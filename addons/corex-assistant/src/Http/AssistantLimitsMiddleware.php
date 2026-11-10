<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Http;

defined('ABSPATH') || exit;

use Corex\Assistant\Limits\VisitorLimits;
use Corex\Assistant\Limits\VisitorLock;
use Corex\Http\Middleware\Middleware;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;

/**
 * The assistant route's own throttle (spec 129, User Story 5): one question at a time per visitor, and
 * the hourly, daily and site-wide counts. The visitor is the request's throttle key, a salted hash
 * ({@see \Corex\Assistant\Limits\VisitorKey}).
 *
 * A refusal is a 429 the controller answers as "busy", so the chat offers the team rather than an
 * error. The lock is released however the rest of the pipeline ends.
 */
final class AssistantLimitsMiddleware implements Middleware
{
    public const REFUSED = 429;

    public function __construct(
        private readonly VisitorLock $lock,
        private readonly VisitorLimits $limits,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $visitor = $request->throttleKey;
        if (! $this->lock->take($visitor)) {
            return Response::reject('One question at a time.', self::REFUSED);
        }

        try {
            if (! $this->limits->allow($visitor)) {
                return Response::reject('Question limit reached.', self::REFUSED);
            }

            return $next($request);
        } finally {
            $this->lock->release($visitor);
        }
    }
}
