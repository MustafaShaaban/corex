<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Http;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantService;
use Corex\Assistant\Limits\VisitorKey;
use Corex\Assistant\Reply\Conversation;
use Corex\Http\ClientAddress;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `POST corex/v1/assistant` (spec 129, FR-007): a thin controller.
 *
 * It refuses an oversized body unread, reads the shape of the conversation, and runs the declared
 * middleware (the page's REST token, then the assistant's limits) before the service answers. Public,
 * as the forms routes are: the token stands for intent, and the limits for abuse.
 */
final class AssistantController
{
    public const ROUTE_NAMESPACE = 'corex/v1';
    public const ROUTE = '/assistant';

    /** Twelve full messages and their JSON fit well inside this; anything bigger is refused unread. */
    public const MAX_BODY_BYTES = 16000;

    /**
     * @param \Closure(): string $salt what the visitor's address is hashed with
     */
    public function __construct(
        private readonly AssistantService $service,
        private readonly Pipeline $pipeline,
        private readonly MiddlewareResolver $middleware,
        private readonly AssistantLimitsMiddleware $limits,
        private readonly ClientAddress $client,
        private readonly \Closure $salt,
    ) {
    }

    public function register(): void
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE, [
            'methods' => 'POST',
            'callback' => [$this, 'ask'],
            // Public endpoint: intent is the nonce middleware, abuse the limits middleware (Principle VII).
            'permission_callback' => '__return_true',
            // Not a public submission (spec 124, FR-004a, DECISIONS #522): the chat stores nothing
            // a person sent in a form, so Forms paused keeps it answering. Its hand-off is the
            // callback form, posted to the forms route, which declares the public submission
            // marker and is refused in Forms paused and Maintenance.
        ]);
    }

    public function ask(WP_REST_Request $request): WP_REST_Response
    {
        if (strlen((string) $request->get_body()) > self::MAX_BODY_BYTES) {
            return self::reply(['code' => 'too_long'], 413);
        }

        $input = (array) $request->get_json_params();
        $messages = Conversation::from($input['messages'] ?? null);
        if (! Conversation::asks($messages)) {
            return self::reply(['code' => 'invalid'], 400);
        }
        $locale = Conversation::locale($input['locale'] ?? '');

        $response = $this->pipeline->run(
            new Request(
                method: 'POST',
                nonce: (string) $request->get_header('X-WP-Nonce'),
                nonceAction: 'wp_rest',
                throttleKey: VisitorKey::of($this->client->current(), ($this->salt)()),
            ),
            fn (): Response => Response::ok($this->service->answer($messages, $locale)),
            ...[...$this->middleware->resolveAll(['nonce']), $this->limits],
        );

        return $this->toRest($response);
    }

    private function toRest(Response $response): WP_REST_Response
    {
        if ($response->isOk()) {
            return self::reply((array) $response->value, 200);
        }

        // A limit is not an error to the visitor: the chat offers the team instead.
        return $response->status === AssistantLimitsMiddleware::REFUSED
            ? self::reply(AssistantService::busy(), 200)
            : self::reply(['code' => $response->status === 403 ? 'expired' : 'error'], $response->status);
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body, int $status): WP_REST_Response
    {
        $response = new WP_REST_Response($body, $status);
        $response->header('Cache-Control', 'no-store');

        return $response;
    }
}
