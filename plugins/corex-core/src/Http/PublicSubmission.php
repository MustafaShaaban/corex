<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

/**
 * The declaration a REST route makes when it takes a submission from the public site: a form, a
 * flow, a sign-up, a booking or job application, an account registration.
 *
 * A route declares it in its own `register_rest_route()` arguments, beside its callback:
 *
 *     register_rest_route('corex/v1', '/newsletter/subscribe', [
 *         'methods'               => 'POST',
 *         'callback'              => [$this, 'subscribe'],
 *         'permission_callback'   => '__return_true',
 *         PublicSubmission::MARKER => true,
 *     ]);
 *
 * WordPress keeps the key in the route's handler, so whatever closes the public site (Maintenance,
 * spec 112) reads it there before any handler runs. A route that does not declare it is never
 * closed by it; sign-in and password reset leave it out on purpose.
 */
final class PublicSubmission
{
    public const MARKER = 'corex_public_submission';

    /**
     * Whether a matched REST handler declared itself a public submission.
     *
     * @param array<string,mixed> $handler
     */
    public static function isDeclaredBy(array $handler): bool
    {
        return ($handler[self::MARKER] ?? false) === true;
    }
}
