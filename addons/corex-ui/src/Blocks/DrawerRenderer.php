<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders the corex/drawer block (spec 068, US9) — a slide-in panel: a trigger button + a native
 * `<dialog>` (focus-trap + ESC + `::backdrop` for free) labelled by its heading, sliding from the
 * start or end edge (logical, RTL-correct), with a close button. Token-only (radius/shadow/z-index/
 * focus), escaped, i18n. Without JS the dialog markup is still present and the content is never lost.
 * Empty title + empty content render nothing.
 */
final class DrawerRenderer implements BlockRenderer
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

        $side = ($attributes['side'] ?? 'end') === 'start' ? 'start' : 'end';

        $id      = uniqid('corex-drawer-');
        $titleId = $id . '-title';

        $heading    = $title !== ''
            ? sprintf('<h2 id="%s" class="corex-drawer__title">%s</h2>', esc_attr($titleId), esc_html($title))
            : '';
        $labelledBy = $title !== '' ? sprintf(' aria-labelledby="%s"', esc_attr($titleId)) : '';

        return sprintf(
            '<div class="corex-drawer-wrap">'
            . '<button type="button" class="corex-drawer__trigger" aria-haspopup="dialog" aria-controls="%1$s">%2$s</button>'
            . '<dialog id="%1$s" class="corex-drawer corex-drawer--%7$s"%3$s>'
            . '%4$s'
            . '<div class="corex-drawer__body">%5$s</div>'
            . '<button type="button" class="corex-drawer__close" data-corex-drawer-close aria-label="%6$s">&times;</button>'
            . '</dialog></div>',
            esc_attr($id),
            esc_html($triggerLabel),
            $labelledBy,
            $heading,
            // Inline-edited (RichText) body — safe inline HTML, scrubbed by wp_kses_post.
            wp_kses_post($body),
            esc_attr__('Close', 'corex'),
            esc_attr($side),
        );
    }
}
