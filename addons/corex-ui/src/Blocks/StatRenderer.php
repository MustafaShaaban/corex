<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a single statistic — a prominent value, a label, and an optional supporting
 * line — as accessible, escaped, token-styled markup. Empty value AND label render
 * nothing (graceful default).
 */
final class StatRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $value = trim((string) ($attributes['value'] ?? ''));
        $label = trim((string) ($attributes['label'] ?? ''));
        $desc  = trim((string) ($attributes['description'] ?? ''));

        if ($value === '' && $label === '') {
            return '';
        }

        // Fields are edited inline with RichText (spec 029), so they may carry safe inline
        // HTML (bold/italic/links) — escape with wp_kses_post, not esc_html.
        $html = '<div class="corex-stat">';

        if ($value !== '') {
            $html .= sprintf('<span class="corex-stat__value">%s</span>', wp_kses_post($value));
        }

        if ($label !== '') {
            $html .= sprintf('<span class="corex-stat__label">%s</span>', wp_kses_post($label));
        }

        if ($desc !== '') {
            $html .= sprintf('<p class="corex-stat__desc">%s</p>', wp_kses_post($desc));
        }

        return $html . '</div>';
    }
}
