<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders an accessible alert (spec 051): a `role="alert"` region with the message and a
 * variant (info/success/warning/error) by class — token-only, escaped, RTL. An empty
 * message renders nothing (graceful default).
 */
final class AlertRenderer implements BlockRenderer
{
    private const VARIANTS = ['info', 'success', 'warning', 'error'];

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $message = trim((string) ($attributes['message'] ?? ''));

        if ($message === '') {
            return '';
        }

        $variant = (string) ($attributes['variant'] ?? 'info');
        if (! in_array($variant, self::VARIANTS, true)) {
            $variant = 'info';
        }

        return sprintf(
            '<div class="corex-alert corex-alert--%s" role="alert">%s</div>',
            esc_attr($variant),
            // Inline-edited (RichText) — safe inline HTML allowed, scrubbed by wp_kses_post.
            wp_kses_post($message),
        );
    }
}
