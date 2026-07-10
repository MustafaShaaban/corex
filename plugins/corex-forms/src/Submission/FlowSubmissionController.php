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
use Corex\Http\ResponseEnvelope;
use DomainException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Public, middleware-secured REST boundary for published flow submissions.
 */
final readonly class FlowSubmissionController
{
    public function __construct(
        private FlowVisitorSubmissionService $submissions,
        private Pipeline $pipeline,
        private MiddlewareResolver $middleware,
    ) {
    }

    public function register(): void
    {
        register_rest_route('corex/v1', '/flows/(?P<id>\d+)/submit', [
            'methods' => 'POST',
            'callback' => [$this, 'submit'],
            'permission_callback' => '__return_true',
            'args' => ['id' => ['sanitize_callback' => 'absint']],
        ]);
    }

    public function submit(WP_REST_Request $request): WP_REST_Response
    {
        try {
            return $this->dispatch($request);
        } catch (DomainException) {
            return new WP_REST_Response(
                ResponseEnvelope::error(
                    'flow_unavailable',
                    __('This flow is not available for submissions.', 'corex'),
                )->toArray(),
                404,
            );
        }
    }

    private function dispatch(WP_REST_Request $request): WP_REST_Response
    {
        $flowId = absint($request->get_param('id'));
        $corexRequest = new Request(
            method: 'POST',
            input: $this->payload($request),
            nonce: (string) $request->get_header('X-WP-Nonce'),
            nonceAction: 'wp_rest',
            throttleKey: 'corex_flow_' . $flowId . '_' . $this->clientFingerprint(),
        );
        $response = $this->pipeline->run(
            $corexRequest,
            fn (Request $safe): Response => $this->run($flowId, $safe->input),
            ...$this->middlewareFor($flowId),
        );

        return $this->toRest($response);
    }

    /** @param array<string,mixed> $values */
    private function run(int $flowId, array $values): Response
    {
        $result = $this->submissions->submit($flowId, $values);
        if (! $result->pipeline->completed) {
            $failed = $result->pipeline->stages[array_key_last($result->pipeline->stages)];
            $status = in_array($failed->key, ['validation', 'protection'], true) ? 422 : 502;

            return Response::reject($failed->message, $status, $this->resultData($result));
        }

        return Response::ok($this->resultData($result));
    }

    /** @return list<Middleware> */
    private function middlewareFor(int $flowId): array
    {
        return [
            ...$this->middleware->resolveAll(['nonce']),
            new SanitizeMiddleware($this->sanitizeShape($this->submissions->schema($flowId))),
            ...$this->middleware->resolveAll(['throttle']),
        ];
    }

    /**
     * @param array<string,FieldSchema> $schema
     * @return array<string,callable|string>
     */
    private function sanitizeShape(array $schema): array
    {
        $shape = [
            FormSubmissionService::HONEYPOT_KEY => 'sanitize_text_field',
            'captcha_token' => 'sanitize_text_field',
            'utm_source' => 'sanitize_text_field',
            'utm_medium' => 'sanitize_text_field',
            'utm_campaign' => 'sanitize_text_field',
            'utm_term' => 'sanitize_text_field',
            'utm_content' => 'sanitize_text_field',
            'page_url' => 'esc_url_raw',
            'referrer' => 'esc_url_raw',
            'locale' => 'sanitize_key',
        ];
        foreach ($schema as $name => $field) {
            $shape[$name] = match ($field->type) {
                'email' => 'sanitize_email',
                'textarea' => 'sanitize_textarea_field',
                'url' => 'esc_url_raw',
                'number', 'rating' => 'floatval',
                'multi-select', 'checkbox-group' => $this->sanitizeList(...),
                default => 'sanitize_text_field',
            };
        }

        return $shape;
    }

    /** @return list<string> */
    private function sanitizeList(mixed $value): array
    {
        return array_values(array_map('sanitize_text_field', is_array($value) ? $value : []));
    }

    /** @return array<string,mixed> */
    private function resultData(FlowVisitorResult $result): array
    {
        return [
            'completed' => $result->pipeline->completed,
            'submission_id' => $result->pipeline->context->submissionId,
            'success' => $result->success,
            'errors' => (array) ($result->pipeline->context->metadata['validation_errors'] ?? []),
            'stages' => array_map(
                static fn (SubmissionStageResult $stage): array => [
                    'key' => $stage->key,
                    'state' => $stage->state,
                    'message' => $stage->message,
                    'retryable' => $stage->retryable,
                ],
                $result->pipeline->stages,
            ),
        ];
    }

    private function toRest(Response $response): WP_REST_Response
    {
        $data = is_array($response->value) ? $response->value : [];
        if ($response->isOk()) {
            $envelope = ResponseEnvelope::success($data);

            return new WP_REST_Response($envelope->toArray(), $response->status);
        }
        $errors = (array) ($data['errors'] ?? []);
        unset($data['errors']);
        if ($errors !== []) {
            $body = ResponseEnvelope::validation($errors, $response->reason)->toArray();
            $body['details'] = $data;

            return new WP_REST_Response($body, $response->status);
        }
        $envelope = ResponseEnvelope::error('flow_submission_failed', $response->reason, $data);

        return new WP_REST_Response($envelope->toArray(), $response->status);
    }

    /** @return array<string,mixed> */
    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) && $json !== [] ? $json : (array) $request->get_body_params();
    }

    private function clientFingerprint(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        return md5($ip);
    }
}
