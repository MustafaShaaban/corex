<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks\Examples;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * The example block's server render. Thin: it wraps the (binding-resolved) value
 * in escaped markup — no data-source calls or business rules (spec FR-009).
 */
final class EntityFieldRenderer implements BlockRenderer
{
    public function render(array $attributes, string $content, object $block): string
    {
        $value = isset($attributes['value']) ? (string) $attributes['value'] : '';

        if ($value === '') {
            return '';
        }

        return sprintf('<div class="corex-entity-field">%s</div>', esc_html($value));
    }
}
