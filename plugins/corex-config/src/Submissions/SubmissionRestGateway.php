<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

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
use WP_REST_Request;
use WP_REST_Response;

/**
 * Applies the shared nonce pipeline and canonical envelopes to Inbox REST actions.
 */
final readonly class SubmissionRestGateway
{
    public function __construct(
        private Pipeline $pipeline,
        private MiddlewareResolver $middleware,
        private SubmissionAccessPolicy $access,
    ) {
    }

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
        if ($this->access->scopeFor(get_current_user_id()) === null) {
            return $this->toRest(Response::reject(__('You cannot manage submissions.', 'corex'), 403));
        }
        $safe = new Request(
            method: $request->get_method(),
            input: $this->payload($request),
            nonce: (string) $request->get_header('X-WP-Nonce'),
            nonceAction: 'wp_rest',
        );
        $domainHandler = fn (Request $input): Response => $this->domainResponse($handler, $input);

        return $this->toRest($this->pipeline->run($safe, $domainHandler, ...$middleware));
    }

    /** @param callable(Request):Response $handler */
    private function domainResponse(callable $handler, Request $request): Response
    {
        try {
            return $handler($request);
        } catch (DomainException $exception) {
            $message = strtolower($exception->getMessage());
            $status = match (true) {
                str_contains($message, 'changed') => 409,
                str_contains($message, 'not found'), str_contains($message, 'unavailable') => 404,
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

        $code = match ($response->status) {
            403 => 'forbidden',
            409 => 'submission_conflict',
            404 => 'submission_not_found',
            422 => 'submission_invalid',
            default => 'submission_error',
        };

        return new WP_REST_Response(ResponseEnvelope::error($code, $response->reason)->toArray(), $response->status);
    }

    /** @return array<string,mixed> */
    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) && $json !== [] ? $json : (array) $request->get_body_params();
    }
}
