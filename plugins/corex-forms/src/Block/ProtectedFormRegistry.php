<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Block;

defined('ABSPATH') || exit;

/**
 * Records which forms on the current request need captcha protection.
 *
 * This is the declarative projection that replaces scanning page content for forms. As each
 * protected form renders it declares its slug and action here; a footer-time asset controller
 * then enqueues the provider script once if — and only if — anything was declared. A page with
 * no protected form declares nothing, so nothing loads (FR-001).
 *
 * Request-scoped: bound as a container singleton, it lives for one page render.
 */
final class ProtectedFormRegistry
{
    /** @var array<string,string> slug => action */
    private array $forms = [];

    public function declare(string $slug, string $action): void
    {
        // Keyed by slug, so the same form appearing twice on a page is declared once.
        $this->forms[$slug] = $action;
    }

    public function isEmpty(): bool
    {
        return $this->forms === [];
    }

    /** @return array<string,string> slug => action */
    public function all(): array
    {
        return $this->forms;
    }
}
