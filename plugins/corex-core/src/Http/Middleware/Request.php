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
     * @param array<string, mixed>                                                                  $input
     * @param array<string, array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int}> $files
     */
    public function __construct(
        public readonly string $method,
        public readonly array $input = [],
        public readonly string $nonce = '',
        public readonly string $nonceAction = '',
        public readonly string $throttleKey = '',
        /**
         * Uploaded file descriptors, carried beside `$input` rather than inside it (spec 081).
         *
         * A separate channel because `SanitizeMiddleware` reduces `$input` to a declared shape of
         * scalar sanitizers, and a `$_FILES` entry run through `sanitize_text_field()` becomes an
         * empty string. Merging the two would mean every sanitizer in the codebase had to learn
         * what a file looks like; keeping them apart means none of them do.
         */
        public readonly array $files = [],
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): self
    {
        return new self(
            $this->method,
            $input,
            $this->nonce,
            $this->nonceAction,
            $this->throttleKey,
            $this->files,
        );
    }
}
