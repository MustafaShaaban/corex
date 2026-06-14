<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a small labelled badge (spec 051) — a token-styled span. An empty label renders
 * nothing (graceful default). Escaped, RTL.
 */
final class BadgeRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $label = trim((string) ($attributes['label'] ?? ''));

        if ($label === '') {
            return '';
        }

        return sprintf('<span class="corex-badge">%s</span>', wp_kses_post($label));
    }
}
