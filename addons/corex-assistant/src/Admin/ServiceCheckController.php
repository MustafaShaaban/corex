<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\ResponseEnvelope;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `POST corex/v1/assistant/check` (spec 129, FR-014): "Check services" on CoreX → Assistant.
 * Administrators only: the capability is asked at the route and again in the declared middleware,
 * with the page's REST token.
 */
final class ServiceCheckController
{
    public const CAPABILITY = 'manage_options';

    public function __construct(
        private readonly ServiceCheck $check,
        private readonly Pipeline $pipeline,
        private readonly MiddlewareResolver $middleware,
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/assistant/check', [
            'methods' => 'POST',
            'callback' => [$this, 'check'],
            'permission_callback' => static fn (): bool => current_user_can(self::CAPABILITY),
        ]);
    }

    public function check(WP_REST_Request $request): WP_REST_Response
    {
        $response = $this->pipeline->run(
            new Request(method: 'POST', nonce: (string) $request->get_header('X-WP-Nonce'), nonceAction: 'wp_rest'),
            fn (): Response => Response::ok($this->check->run()),
            ...$this->middleware->resolveAll(['auth:' . self::CAPABILITY, 'nonce']),
        );

        $envelope = $response->isOk()
            ? ResponseEnvelope::success(['results' => $response->value])
            : ResponseEnvelope::error('forbidden', $response->reason);

        return new WP_REST_Response($envelope->toArray(), $response->isOk() ? 200 : $response->status);
    }
}
