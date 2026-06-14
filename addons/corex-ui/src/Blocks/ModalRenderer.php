<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders the corex/modal block (spec 054, US3) — the one justified new DLS component: a
 * trigger button + a native `<dialog>` (focus-trap + ESC + `::backdrop` for free) labelled by
 * its heading, with a close button. Token-only (radius/shadow/z-index/focus), escaped, i18n,
 * RTL. Without JS the dialog markup is still present and the close/trigger are inert anchors —
 * the content is never lost. Empty title + empty content render nothing.
 */
final class ModalRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $title = trim((string) ($attributes['title'] ?? ''));
        $body  = trim((string) ($attributes['content'] ?? ''));

        if ($title === '' && $body === '') {
            return '';
        }

        $triggerLabel = trim((string) ($attributes['triggerLabel'] ?? ''));
        if ($triggerLabel === '') {
            $triggerLabel = __('Open', 'corex');
        }

        $id      = uniqid('corex-modal-');
        $titleId = $id . '-title';

        $heading    = $title !== ''
            ? sprintf('<h2 id="%s" class="corex-modal__title">%s</h2>', esc_attr($titleId), esc_html($title))
            : '';
        $labelledBy = $title !== '' ? sprintf(' aria-labelledby="%s"', esc_attr($titleId)) : '';

        return sprintf(
            '<div class="corex-modal-wrap">'
            . '<button type="button" class="corex-modal__trigger" aria-haspopup="dialog" aria-controls="%1$s">%2$s</button>'
            . '<dialog id="%1$s" class="corex-modal"%3$s>'
            . '%4$s'
            . '<div class="corex-modal__body">%5$s</div>'
            . '<button type="button" class="corex-modal__close" data-corex-modal-close aria-label="%6$s">&times;</button>'
            . '</dialog></div>',
            esc_attr($id),
            esc_html($triggerLabel),
            $labelledBy,
            $heading,
            // Inline-edited (RichText) body — safe inline HTML, scrubbed by wp_kses_post.
            wp_kses_post($body),
            esc_attr__('Close', 'corex'),
        );
    }
}
