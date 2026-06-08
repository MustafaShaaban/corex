<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Http\Middleware;

defined('ABSPATH') || exit;

/**
 * The minimal, immutable request context a middleware reads (spec data model R2).
 */
final class Request
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly string $method,
        public readonly array $input = [],
        public readonly string $nonce = '',
        public readonly string $nonceAction = '',
        public readonly string $throttleKey = '',
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): self
    {
        return new self($this->method, $input, $this->nonce, $this->nonceAction, $this->throttleKey);
    }
}
