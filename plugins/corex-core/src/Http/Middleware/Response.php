<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * What a middleware or handler returns: an allowed result, or a rejection that
 * short-circuits the pipeline (spec FR-003).
 */
final class Response
{
    private function __construct(
        private readonly bool $ok,
        public readonly mixed $value,
        public readonly string $reason,
        public readonly int $status,
    ) {
    }

    public static function ok(mixed $value = null): self
    {
        return new self(true, $value, '', 200);
    }

    /**
     * @param mixed $payload optional structured body for the rejection (e.g. per-field
     *                       validation errors); defaults to null for opaque rejections
     */
    public static function reject(string $reason, int $status = 403, mixed $payload = null): self
    {
        return new self(false, $payload, $reason, $status);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }
}
