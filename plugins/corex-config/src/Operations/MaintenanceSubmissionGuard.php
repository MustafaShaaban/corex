<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Http\PublicSubmission;
use WP_Error;

/**
 * Closes public submissions while the site is in Maintenance (spec 112, User Story 3).
 *
 * A REST route that declares {@see PublicSubmission::MARKER} is answered 503 with `Retry-After`,
 * before its permission callback and its handler run, so nothing is stored, mailed or counted
 * against a throttle. Routes that do not declare it (sign-in, password reset, every read) are
 * untouched, and so is every mode but Maintenance: Coming soon keeps its forms open (spec 101).
 */
final class MaintenanceSubmissionGuard
{
    public const CODE = 'site_in_maintenance';

    private const RETRY_AFTER_SECONDS = 3600;

    public function __construct(private readonly MaintenanceDecision $decision)
    {
    }

    public function register(): void
    {
        add_filter('rest_request_before_callbacks', [$this, 'refuse'], 0, 2);
        add_filter('rest_request_after_callbacks', [$this, 'retryAfter'], 10, 1);
    }

    /**
     * @param mixed               $response What the REST server has so far: null, or an error.
     * @param array<string,mixed> $handler
     */
    public function refuse(mixed $response, array $handler): mixed
    {
        if (! PublicSubmission::isDeclaredBy($handler) || ! $this->decision->closedToCurrentVisitor()) {
            return $response;
        }

        return new WP_Error(
            self::CODE,
            __('The site is closed for maintenance and is not taking submissions. Please try again later.', 'corex'),
            ['status' => 503],
        );
    }

    /**
     * The refusal leaves the REST server as a response carrying `Retry-After`, as the maintenance
     * page does; an error cannot carry a header.
     */
    public function retryAfter(mixed $response): mixed
    {
        if (! $response instanceof WP_Error || $response->get_error_code() !== self::CODE) {
            return $response;
        }

        $answer = rest_convert_error_to_response($response);
        $answer->header('Retry-After', (string) self::RETRY_AFTER_SECONDS);

        return $answer;
    }
}
