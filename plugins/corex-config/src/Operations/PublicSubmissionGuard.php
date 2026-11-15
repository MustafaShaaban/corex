<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Http\PublicSubmission;
use Corex\Http\PublicSubmissionPause;
use WP_Error;

/**
 * Closes public submissions while the site is in Maintenance (spec 112, User Story 3) or Forms
 * paused (spec 124, FR-004): one gate for both modes, so they cannot disagree about which endpoints
 * are closed.
 *
 * A REST route that declares {@see PublicSubmission::MARKER} is answered 503 with `Retry-After`,
 * before its permission callback and its handler run, so nothing is stored, mailed or counted
 * against a throttle. The answer carries the mode's code and message and no `errors` object, which
 * is how a form tells a refusal from a field it has to correct. Routes that do not declare it
 * (sign-in, password reset, every read, the Assistant's chat) are untouched, and so is every other
 * mode: Coming soon keeps its forms open (spec 101). Who passes is {@see PublicSubmissionPause}'s
 * answer.
 */
final class PublicSubmissionGuard
{
    public function __construct(private readonly PublicSubmissionPause $pause)
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
        if (! PublicSubmission::isDeclaredBy($handler)) {
            return $response;
        }

        $mode = $this->pause->closedBy();
        if ($mode === null) {
            return $response;
        }

        return new WP_Error($this->pause->code($mode), $this->pause->message($mode), ['status' => 503]);
    }

    /**
     * The refusal leaves the REST server as a response carrying `Retry-After`, as the maintenance
     * page does; an error cannot carry a header.
     */
    public function retryAfter(mixed $response): mixed
    {
        if (! $response instanceof WP_Error || ! $this->isRefusal($response)) {
            return $response;
        }

        $answer = rest_convert_error_to_response($response);
        $answer->header('Retry-After', (string) $this->pause->retryAfter());

        return $answer;
    }

    private function isRefusal(WP_Error $error): bool
    {
        $codes = [
            $this->pause->code(PublicSubmissionPause::MAINTENANCE),
            $this->pause->code(PublicSubmissionPause::FORMS_PAUSED),
        ];

        $data = $error->get_error_data();

        return in_array($error->get_error_code(), $codes, true)
            && is_array($data)
            && ($data['status'] ?? null) === 503;
    }
}
