<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * Classifies a PageSpeed Insights attempt (spec 044, US3) into a specific, actionable
 * cause — replacing the old generic "could not be read" message. Pure: given whether the
 * URL is public, the HTTP status, and the decoded body, it returns a kind, a user message,
 * an **admin-only** raw detail (scrubbed of any `key=`/token — never a secret), and whether
 * the API key is optional or recommended.
 */
final class PsiDiagnostic
{
    public const OK               = 'ok';
    public const LOCAL_URL        = 'local_url';
    public const HTTP_ERROR       = 'http_error';
    public const QUOTA            = 'quota';
    public const INVALID_KEY      = 'invalid_key';
    public const INVALID_RESPONSE = 'invalid_response';

    private function __construct(
        public readonly string $kind,
        public readonly string $message,
        public readonly string $detail,
        public readonly string $keyAdvice,
    ) {
    }

    /**
     * @param array<string,mixed>|null $body the decoded PSI response body, or null
     */
    public static function classify(bool $isPublic, int $httpStatus, ?array $body): self
    {
        $kind = self::kindFor($isPublic, $httpStatus, $body);
        $detail = self::scrub(sprintf('HTTP %d — %s', $httpStatus, self::bodyMessage($body)));

        return new self($kind, self::messageFor($kind, $httpStatus), $detail, 'recommended');
    }

    /**
     * @param array<string,mixed>|null $body
     */
    private static function kindFor(bool $isPublic, int $httpStatus, ?array $body): string
    {
        if (! $isPublic) {
            return self::LOCAL_URL;
        }
        if ($httpStatus === 429 || self::looksLikeQuota($body)) {
            return self::QUOTA;
        }
        if (in_array($httpStatus, [400, 403], true)) {
            return self::looksLikeKeyError($body) ? self::INVALID_KEY : self::HTTP_ERROR;
        }
        if ($httpStatus >= 500) {
            return self::HTTP_ERROR;
        }
        if ($body === null || ! isset($body['lighthouseResult'])) {
            return self::INVALID_RESPONSE;
        }

        return self::OK;
    }

    private static function messageFor(string $kind, int $httpStatus): string
    {
        return match ($kind) {
            self::LOCAL_URL        => __('PageSpeed cannot crawl a local or private URL. Test a public URL instead.', 'corex'),
            self::QUOTA            => __('PageSpeed quota was exceeded. Add an API key, or wait and try again.', 'corex'),
            self::INVALID_KEY      => __('The PageSpeed API key was rejected. Check the key in Settings.', 'corex'),
            self::INVALID_RESPONSE => __('PageSpeed returned an unreadable response. Try again shortly.', 'corex'),
            self::OK               => __('PageSpeed is reachable.', 'corex'),
            default                => sprintf(
                /* translators: %d: HTTP status code */
                __('PageSpeed returned HTTP %d. Try again shortly.', 'corex'),
                $httpStatus,
            ),
        };
    }

    /**
     * @param array<string,mixed>|null $body
     */
    private static function looksLikeKeyError(?array $body): bool
    {
        $message = strtolower(self::bodyMessage($body));

        return str_contains($message, 'api key') || str_contains($message, 'keyinvalid') || str_contains($message, 'api_key');
    }

    /**
     * @param array<string,mixed>|null $body
     */
    private static function looksLikeQuota(?array $body): bool
    {
        $message = strtolower(self::bodyMessage($body));

        return str_contains($message, 'quota') || str_contains($message, 'rate limit') || str_contains($message, 'ratelimit');
    }

    /**
     * @param array<string,mixed>|null $body
     */
    private static function bodyMessage(?array $body): string
    {
        if (! is_array($body)) {
            return '';
        }

        $error = $body['error'] ?? null;

        if (is_array($error) && isset($error['message'])) {
            return (string) $error['message'];
        }

        return '';
    }

    /**
     * Remove any `key=…` / token-bearing query parameter from the admin detail so a secret
     * can never appear (FR-013).
     */
    private static function scrub(string $detail): string
    {
        return (string) preg_replace('/\b(key|token|secret)=[^\s&]+/i', '$1=***', $detail);
    }
}
