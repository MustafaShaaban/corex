<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\Data;
defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Http\Middleware\Middleware;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\Middleware\SanitizeMiddleware;
use Corex\Http\ResponseEnvelope;
use DomainException;
use InvalidArgumentException;
use WP_REST_Request;
use WP_REST_Response;

/** Shared authentication, nonce, sanitation, and canonical envelope boundary for Data REST. */
final readonly class DataRestGateway
{
    public function __construct(private Pipeline $pipeline, private MiddlewareResolver $middleware) {}

    /** @param callable(Request):Response $handler */
    public function read(WP_REST_Request $request, callable $handler): WP_REST_Response
    {
        return $this->authorized($request, $handler, []);
    }

    /** @param array<string,callable|string> $shape @param callable(Request):Response $handler */
    public function mutate(WP_REST_Request $request, array $shape, callable $handler): WP_REST_Response
    {
        return $this->authorized($request, $handler, [
            ...$this->middleware->resolveAll(['nonce']),
            new SanitizeMiddleware($shape),
        ]);
    }

    /** @param callable(Request):Response $handler @param list<Middleware> $middleware */
    private function authorized(WP_REST_Request $request, callable $handler, array $middleware): WP_REST_Response
    {
        if (! $this->canManage()) {
            return $this->toRest(Response::reject(__('You cannot manage CoreX data.', 'corex'), 403));
        }
        $safe = new Request(
            method: $request->get_method(), input: $this->payload($request),
            nonce: (string) $request->get_header('X-WP-Nonce'), nonceAction: 'wp_rest',
        );
        $domain = fn (Request $input): Response => $this->domainResponse($handler, $input);

        return $this->toRest($this->pipeline->run($safe, $domain, ...$middleware));
    }

    public function canManage(): bool
    {
        return current_user_can(CorexAbility::MANAGE_DATA)
            || current_user_can(CorexAbility::MANAGE_DATA_MODELS)
            || current_user_can('manage_options');
    }

    /** @param callable(Request):Response $handler */
    private function domainResponse(callable $handler, Request $request): Response
    {
        try {
            return $handler($request);
        } catch (DomainException $exception) {
            $message = strtolower($exception->getMessage());
            $status = match (true) {
                str_contains($message, 'permission') => 403,
                str_contains($message, 'unavailable'), str_contains($message, 'not found') => 404,
                str_contains($message, 'expired'), str_contains($message, 'already used'),
                str_contains($message, 'changed'), str_contains($message, 'cannot be committed') => 409,
                default => 422,
            };

            return Response::reject($exception->getMessage(), $status);
        } catch (InvalidArgumentException $exception) {
            return Response::reject($exception->getMessage(), 422);
        }
    }

    private function toRest(Response $response): WP_REST_Response
    {
        if ($response->isOk()) {
            return new WP_REST_Response(ResponseEnvelope::success((array) $response->value)->toArray(), $response->status);
        }

        return new WP_REST_Response(
            ResponseEnvelope::error('data_' . ($response->status === 403 ? 'forbidden' : 'invalid'), $response->reason)->toArray(),
            $response->status,
        );
    }

    /** @return array<string,mixed> */
    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        if (is_array($json) && $json !== []) {
            return $json;
        }

        if ($request->get_method() === 'GET') {
            return $request->get_params();
        }

        return array_replace((array) $request->get_body_params(), (array) $request->get_file_params());
    }
}
