<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http;

defined('ABSPATH') || exit;

/**
 * The one canonical shape of every Corex response (spec 043). A pure, immutable value
 * object built only via the named factories — never holds a secret, so it is safe to
 * serialise straight to the wire (Principle VII).
 *
 *   success    → { ok:true,  message, data }
 *   validation → { ok:false, code:'validation_failed', message, errors{}, details{} }
 *   error      → { ok:false, code, message, details{} }
 *
 * `EnvelopeResponder` maps it to a `WP_REST_Response`; this object knows nothing about
 * WordPress so it stays headlessly testable.
 */
final class ResponseEnvelope
{
    /**
     * @param array<string,mixed>  $data    success payload (empty on error)
     * @param array<string,string> $errors  field => message (validation only)
     * @param array<string,mixed>  $details safe extra context (error only) — never secrets
     */
    private function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly array $data,
        public readonly string $code,
        public readonly array $errors,
        public readonly array $details,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function success(array $data = [], string $message = ''): self
    {
        return new self(true, $message, $data, '', [], []);
    }

    /**
     * @param array<string,mixed> $details safe context only (never a secret)
     */
    public static function error(string $code, string $message, array $details = []): self
    {
        return new self(false, $message, [], $code, [], $details);
    }

    /**
     * @param array<string,string> $errors field => translatable message
     */
    public static function validation(array $errors, string $message = ''): self
    {
        return new self(false, $message, [], 'validation_failed', $errors, []);
    }

    /**
     * The wire body — only the documented contract keys, in a stable order.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        if ($this->ok) {
            return [
                'ok'      => true,
                'message' => $this->message,
                'data'    => $this->data,
            ];
        }

        $body = [
            'ok'      => false,
            'code'    => $this->code,
            'message' => $this->message,
        ];

        if ($this->errors !== []) {
            $body['errors'] = $this->errors;
        }

        $body['details'] = $this->details;

        return $body;
    }
}
