<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin\Errors;

defined('ABSPATH') || exit;

/**
 * One refusal, described once (spec 083, FR-004).
 *
 * The reason this exists rather than each call site building its own page: spec 079 left six
 * hand-written standalone documents scattered across corex-config, and the denial copy in
 * `AdminPage` drifted from what the screens actually require. One value object rendered by one
 * presenter is what stops the pre-shell document and the screens' own copy disagreeing about
 * status, wording or the way back.
 *
 * Pure: no WordPress calls. The presenter owns escaping and translation.
 *
 * Seven constructor parameters exceeds the usual ceiling of four. That rule's own remedy is "at
 * five, introduce a record/DTO" — this *is* that record, and every field is one indivisible fact
 * about a single refusal, so collapsing any of them into a nested object would only move the
 * argument list somewhere else.
 */
final class AdminError
{
    /**
     * @param string                                            $title    Short, human, already translated.
     * @param string                                            $message  The explanation, already translated.
     * @param list<array{label:string,url:string,primary:bool}> $actions  Ways forward, in order.
     * @param string                                            $detail   Trusted HTML from the original caller —
     *                                                                    WordPress's own message, which may carry a
     *                                                                    back-link (FR-008). Escaped by the presenter.
     * @param string                                            $bodyHtml A complete, already-escaped body that
     *                                                                    replaces the standard anatomy; used for the
     *                                                                    denied surface, which has a form in it.
     */
    public function __construct(
        public readonly AdminErrorKind $kind,
        public readonly string $title,
        public readonly string $message,
        public readonly int $status,
        public readonly array $actions = [],
        public readonly string $detail = '',
        public readonly string $bodyHtml = '',
    ) {
    }

    /**
     * The same error carrying a purpose-built body — the denied surface, which has a form in it.
     */
    public function withBody(string $bodyHtml): self
    {
        return $this->derive(['bodyHtml' => $bodyHtml]);
    }

    /**
     * The same error carrying what the original caller said, verbatim.
     */
    public function withDetail(string $detail): self
    {
        return $this->derive(['detail' => $detail]);
    }

    /**
     * The same error offering a different set of ways forward.
     *
     * @param list<array{label:string,url:string,primary:bool}> $actions
     */
    public function withActions(array $actions): self
    {
        return $this->derive(['actions' => $actions]);
    }

    public function hasBody(): bool
    {
        return $this->bodyHtml !== '';
    }

    /**
     * The one place this class is constructed from an existing instance.
     *
     * Readonly promoted properties cannot be reassigned on a clone before PHP 8.5, so every wither
     * has to call the constructor — and a seven-argument constructor called from three different
     * methods is precisely where an argument silently swaps places. Listing them once means a new
     * field is added in two places (here and the signature) instead of four.
     *
     * @param array<string,mixed> $overrides
     */
    private function derive(array $overrides): self
    {
        return new self(
            $overrides['kind'] ?? $this->kind,
            $overrides['title'] ?? $this->title,
            $overrides['message'] ?? $this->message,
            $overrides['status'] ?? $this->status,
            $overrides['actions'] ?? $this->actions,
            $overrides['detail'] ?? $this->detail,
            $overrides['bodyHtml'] ?? $this->bodyHtml,
        );
    }
}
