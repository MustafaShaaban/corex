<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

use WP_REST_Response;

/**
 * The thin WordPress boundary for a {@see ResponseEnvelope} (spec 043). Maps the
 * envelope's machine code to an HTTP status and wraps the body as a WP_REST_Response.
 * No business logic — greenfield endpoints call `toRest()`; endpoints that already own
 * an authoritative status (e.g. the throttle-aware form submit) reuse `status()` /
 * `ResponseEnvelope::toArray()` directly to preserve their own status.
 */
final class EnvelopeResponder
{
    /**
     * HTTP status for an envelope: success → 200; validation → 422; forbidden → 403;
     * any other error → 400.
     */
    public function status(ResponseEnvelope $envelope): int
    {
        if ($envelope->ok) {
            return 200;
        }

        return match ($envelope->code) {
            'validation_failed' => 422,
            'forbidden'         => 403,
            default             => 400,
        };
    }

    public function toRest(ResponseEnvelope $envelope): WP_REST_Response
    {
        return new WP_REST_Response($envelope->toArray(), $this->status($envelope));
    }
}
