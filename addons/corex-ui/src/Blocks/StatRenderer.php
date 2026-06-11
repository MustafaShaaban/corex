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

        $html = '<div class="corex-stat">';

        if ($value !== '') {
            $html .= sprintf('<span class="corex-stat__value">%s</span>', esc_html($value));
        }

        if ($label !== '') {
            $html .= sprintf('<span class="corex-stat__label">%s</span>', esc_html($label));
        }

        if ($desc !== '') {
            $html .= sprintf('<p class="corex-stat__desc">%s</p>', esc_html($desc));
        }

        return $html . '</div>';
    }
}
