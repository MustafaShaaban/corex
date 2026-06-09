<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Schema\FieldSchema;
use Corex\Http\Middleware\Middleware;
use Corex\Http\Middleware\MiddlewareResolver;
use Corex\Http\Middleware\Pipeline;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\Middleware\SanitizeMiddleware;
use WP_REST_Request;
use WP_REST_Response;

/**
 * The REST boundary for submissions. A thin controller (Principle III): it adapts
 * the WP request, runs it through the declared security middleware (nonce →
 * form-shaped sanitize → throttle), delegates to the submission service, and maps
 * the Response to a WP_REST_Response. Security is the middleware's job, not the
 * controller's (Principle VII) — hence the public permission callback.
 */
final class SubmitController
{
    public function __construct(
        private readonly FormSubmissionService $service,
        private readonly Pipeline $pipeline,
        private readonly MiddlewareResolver $middleware,
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/forms/(?P<slug>[a-z0-9-]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'submit'],
            // Public endpoint: identity/intent are enforced by the nonce + throttle
            // middleware and the honeypot, not by a capability (Principle VII).
            'permission_callback' => '__return_true',
            'args'                => [
                'slug' => ['sanitize_callback' => 'sanitize_key'],
            ],
        ]);
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        $slug = sanitize_key((string) $request['slug']);

        $corexRequest = new Request(
            method: 'POST',
            input: $this->payload($request),
            nonce: (string) $request->get_header('X-WP-Nonce'),
            nonceAction: 'wp_rest',
            throttleKey: 'corex_form_' . $slug . '_' . $this->clientFingerprint(),
        );

        $response = $this->pipeline->run(
            $corexRequest,
            fn (Request $r): Response => $this->service->handle($slug, $r->input, FormSubmissionService::HONEYPOT_KEY),
            ...$this->middlewareFor($slug),
        );

        return $this->toRest($response);
    }

    /**
     * @return list<Middleware> nonce → form-shaped sanitize → throttle
     */
    private function middlewareFor(string $slug): array
    {
        return [
            ...$this->middleware->resolveAll(['nonce']),
            new SanitizeMiddleware($this->sanitizeShape($this->service->schemaFor($slug))),
            ...$this->middleware->resolveAll(['throttle']),
        ];
    }

    /**
     * The generic `sanitize` alias carries no shape; the form's own fields (plus the
     * honeypot) define which keys survive and how each is sanitized.
     *
     * @param array<string,FieldSchema> $schema
     *
     * @return array<string,string> key => WP sanitizer function name
     */
    private function sanitizeShape(array $schema): array
    {
        $shape = [FormSubmissionService::HONEYPOT_KEY => 'sanitize_text_field'];

        foreach ($schema as $name => $field) {
            $shape[$name] = match ($field->type) {
                'email'    => 'sanitize_email',
                'textarea' => 'sanitize_textarea_field',
                default    => 'sanitize_text_field',
            };
        }

        return $shape;
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) ? $json : (array) $request->get_body_params();
    }

    private function clientFingerprint(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        return md5($ip);
    }

    private function toRest(Response $response): WP_REST_Response
    {
        if ($response->isOk()) {
            return new WP_REST_Response(['ok' => true, 'values' => $response->value], 200);
        }

        $body = ['ok' => false, 'message' => $response->reason];

        if (is_array($response->value) && $response->value !== []) {
            $body['errors'] = $response->value;
        }

        return new WP_REST_Response($body, $response->status);
    }
}
