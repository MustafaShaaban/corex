<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Schema\FieldSchema;
use Corex\Http\ResponseEnvelope;
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
            files: $this->uploads($request),
        );

        $response = $this->pipeline->run(
            $corexRequest,
            fn (Request $r): Response => $this->service->handle(
                $slug,
                $r->input,
                FormSubmissionService::HONEYPOT_KEY,
                $r->files,
            ),
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
                'multi-select' => self::sanitizeList(...),
                default    => 'sanitize_text_field',
            };
        }

        return $shape;
    }

    /**
     * Sanitize a value that may legitimately be a list (#148 item 1).
     *
     * Every arm above maps to a scalar sanitizer, and `sanitize_text_field()` returns `''` for an
     * array — so before this, a `<select multiple>` had two possible outcomes and both were wrong:
     * the runtime sent only the first selection and one value was stored, or the runtime sent the
     * real list and the field was blanked entirely. The browser fix and this one only make sense
     * shipped together.
     *
     * A scalar still passes through, so a multi-select with one selection, or a schema whose
     * control was swapped for a single select, behaves exactly as before.
     *
     * @return list<string>|string
     */
    private static function sanitizeList(mixed $value): array|string
    {
        if (! is_array($value)) {
            return sanitize_text_field((string) $value);
        }

        // Values only. A list is what a multi-select submits; preserving submitted keys would let
        // a caller shape the stored array, and nothing downstream reads them.
        return array_values(array_map(
            static fn (mixed $item): string => sanitize_text_field(is_scalar($item) ? (string) $item : ''),
            $value,
        ));
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();

        return is_array($json) ? $json : (array) $request->get_body_params();
    }

    /**
     * The uploaded files, normalised to one descriptor per field (spec 081, FR-002).
     *
     * Read through `WP_REST_Request::get_file_params()` rather than `$_FILES` directly, because the
     * REST server is what populated it and reaching around the object would mean this route behaved
     * differently when driven from a test than from a browser.
     *
     * Only the browser-supplied `name` and `type` are sanitized. `tmp_name` is a path PHP created,
     * verified later by `wp_handle_upload()`'s own `is_uploaded_file()` check; running it through a
     * text sanitizer can alter a legitimate path and break the move for a perfectly good file.
     *
     * A multi-file input (`name="cv[]"`) yields arrays in every slot. Those are skipped rather than
     * half-handled: one file per field is the decided scope, and a descriptor whose `tmp_name` is
     * an array would reach the store as nonsense.
     *
     * @return array<string,array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    private function uploads(WP_REST_Request $request): array
    {
        $files = [];

        foreach ($request->get_file_params() as $field => $descriptor) {
            if (! is_array($descriptor) || is_array($descriptor['tmp_name'] ?? null)) {
                continue;
            }

            $files[sanitize_key((string) $field)] = [
                'name'     => sanitize_file_name((string) ($descriptor['name'] ?? '')),
                'type'     => sanitize_mime_type((string) ($descriptor['type'] ?? '')),
                'tmp_name' => (string) ($descriptor['tmp_name'] ?? ''),
                'error'    => (int) ($descriptor['error'] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($descriptor['size'] ?? 0),
            ];
        }

        return $files;
    }

    private function clientFingerprint(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : '';

        return md5($ip);
    }

    /**
     * Map the middleware Response to the canonical envelope (spec 043), preserving the
     * pipeline's authoritative HTTP status (e.g. 429 from the throttle). Additive: the
     * success body still mirrors `values` at the top level for one release, and the
     * error body keeps `message`/`errors` while adding `code`/`details`.
     */
    private function toRest(Response $response): WP_REST_Response
    {
        if ($response->isOk()) {
            $body = ResponseEnvelope::success(['values' => $response->value])->toArray();
            $body['values'] = $response->value; // back-compat mirror (one release)

            return new WP_REST_Response($body, 200);
        }

        $errors = is_array($response->value) ? $response->value : [];

        $envelope = $errors !== []
            ? ResponseEnvelope::validation($errors, $response->reason)
            : ResponseEnvelope::error($this->codeForStatus($response->status), $response->reason);

        return new WP_REST_Response($envelope->toArray(), $response->status);
    }

    private function codeForStatus(int $status): string
    {
        return match ($status) {
            401, 403 => 'forbidden',
            429      => 'rate_limited',
            default  => 'error',
        };
    }
}
