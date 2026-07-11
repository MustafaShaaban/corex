<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Jobs;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Http\ResponseEnvelope;
use Corex\Jobs\JobService;
use DateTimeImmutable;
use WP_REST_Request;
use WP_REST_Response;

final class JobController
{
    public function __construct(private readonly JobService $jobs)
    {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/jobs/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'canManage'],
        ]);
        register_rest_route('corex/v1', '/jobs/(?P<id>\d+)/cancel', [
            'methods'             => 'POST',
            'callback'            => [$this, 'cancel'],
            'permission_callback' => [$this, 'canMutate'],
        ]);
        register_rest_route('corex/v1', '/jobs/(?P<id>\d+)/retry', [
            'methods'             => 'POST',
            'callback'            => [$this, 'retry'],
            'permission_callback' => [$this, 'canMutate'],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can(CorexAbility::MANAGE_OPERATIONS);
    }

    public function canMutate(WP_REST_Request $request): bool
    {
        return $this->canManage()
            && wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest') !== false;
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $job = $this->jobs->find((int) $request->get_param('id'));

        if ($job === null) {
            return $this->error('job_not_found', __('That bounded job was not found.', 'corex'), 404);
        }

        return new WP_REST_Response(ResponseEnvelope::success(['job' => $job->toArray()])->toArray());
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response
    {
        $job = $this->jobs->cancel((int) $request->get_param('id'), new DateTimeImmutable('now'));

        if ($job === null) {
            return $this->error('job_not_cancellable', __('That job cannot be cancelled.', 'corex'), 409);
        }

        return new WP_REST_Response(ResponseEnvelope::success(['job' => $job->toArray()])->toArray());
    }

    public function retry(WP_REST_Request $request): WP_REST_Response
    {
        $job = $this->jobs->retry((int) $request->get_param('id'), new DateTimeImmutable('now'));

        if ($job === null) {
            return $this->error('job_not_retryable', __('That job cannot be retried.', 'corex'), 409);
        }

        return new WP_REST_Response(ResponseEnvelope::success(['job' => $job->toArray()])->toArray());
    }

    private function error(string $code, string $message, int $status): WP_REST_Response
    {
        return new WP_REST_Response(ResponseEnvelope::error($code, $message)->toArray(), $status);
    }
}
