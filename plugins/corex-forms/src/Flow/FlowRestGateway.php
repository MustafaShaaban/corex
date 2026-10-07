<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Flow;

defined('ABSPATH') || exit;

use Corex\Http\Middleware\Middleware;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\Middleware\SanitizeMiddleware;
use Corex\Http\ResponseEnvelope;
use DomainException;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Applies declared Flow REST middleware and maps domain results to envelopes.
 */
final readonly class FlowRestGateway
{
    /** What it takes to read or change a flow, asked at the route and again in the middleware. */
    private const CAPABILITY = 'manage_options';

    public function __construct(
        private Pipeline $pipeline,
        private MiddlewareResolver $middleware,
    ) {
    }

    /**
     * What every flow route asks before a handler runs. The handlers ask again through the
     * middleware, and a change needs a nonce as well. This is here so the route says it is guarded.
     */
    public function permits(): true|WP_Error
    {
        return current_user_can(self::CAPABILITY)
            ? true
            : new WP_Error(
                'forbidden',
                __('You do not have permission to perform this action.', 'corex'),
                ['status' => 403],
            );
    }

    /** @param callable(Request):Response $handler */
    public function read(WP_REST_Request $request, callable $handler): WP_REST_Response
    {
        $corexRequest = new Request('GET', $request->get_params());

        return $this->execute(
            $corexRequest,
            $handler,
            $this->middleware->resolveAll(['auth:' . self::CAPABILITY]),
        );
    }

    /**
     * @param array<string,callable|string> $shape
     * @param callable(Request):Response    $handler
     */
    public function mutate(WP_REST_Request $request, array $shape, callable $handler): WP_REST_Response
    {
        $corexRequest = new Request(
            method: $request->get_method(),
            input: $this->payload($request),
            nonce: (string) $request->get_header('X-WP-Nonce'),
            nonceAction: 'wp_rest',
        );
        $middleware = [
            ...$this->middleware->resolveAll(['auth:' . self::CAPABILITY, 'nonce']),
            new SanitizeMiddleware($shape),
        ];

        return $this->execute($corexRequest, $handler, $middleware);
    }

    /**
     * @param callable(Request):Response $handler
     * @param list<Middleware>           $middleware
     */
    private function execute(Request $request, callable $handler, array $middleware): WP_REST_Response
    {
        $safeHandler = fn (Request $safeRequest): Response => $this->domainResponse($handler, $safeRequest);
        $response = $this->pipeline->run($request, $safeHandler, ...$middleware);

        return $this->toRest($response);
    }

    /** @param callable(Request):Response $handler */
    private function domainResponse(callable $handler, Request $request): Response
    {
        try {
            return $handler($request);
        } catch (FlowConflictException $exception) {
            return Response::reject($exception->getMessage(), 409, ['code' => 'flow_conflict']);
        } catch (DomainException|InvalidArgumentException $exception) {
            return Response::reject($exception->getMessage(), 422, ['code' => 'flow_invalid']);
        }
    }

    private function toRest(Response $response): WP_REST_Response
    {
        if ($response->isOk()) {
            $data = is_array($response->value) ? $response->value : ['result' => $response->value];

            return new WP_REST_Response(ResponseEnvelope::success($data)->toArray(), $response->status);
        }

        $details = is_array($response->value) ? $response->value : [];
        $code = (string) ($details['code'] ?? $this->errorCode($response->status));
        unset($details['code']);

        return new WP_REST_Response(
            ResponseEnvelope::error($code, $response->reason, $details)->toArray(),
            $response->status,
        );
    }

    /** @return array<string,mixed> */
    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) && $json !== [] ? $json : (array) $request->get_body_params();
    }

    private function errorCode(int $status): string
    {
        return match ($status) {
            403 => 'forbidden',
            409 => 'flow_conflict',
            422 => 'flow_invalid',
            default => 'flow_error',
        };
    }
}
