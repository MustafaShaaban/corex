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
 * `<dialog>` (focus-trap + ESC + `::backdrop` for free), sliding from the start or end edge
 * (logical, RTL-correct), with a close button. Token-only (radius/shadow/z-index/focus), escaped,
 * i18n. Empty title + empty content render nothing.
 *
 * The dialog always has a name. With a title it is labelled by its heading. With none it carries
 * the label of its trigger, which is never empty: a dialog with no name is announced as "dialog"
 * and nothing more (DECISIONS #316).
 *
 * Only the view script answers the trigger and the close button. With scripting off the stylesheet
 * shows the dialog in the page, where the block is, and hides the two buttons
 * (shared/_dialog-parts.scss), so what it holds is not lost.
 *
 * The close button comes before the body, in a header with the title. The order is not for looks:
 * a browser opening a dialog puts focus on the first thing in it that takes focus, and scrolls to
 * it. With the button last that was the first link of the content, wherever in the content it was.
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

        $heading = $title !== ''
            ? sprintf('<h2 id="%s" class="corex-drawer__title">%s</h2>', esc_attr($titleId), esc_html($title))
            : '';
        $name    = $title !== ''
            ? sprintf(' aria-labelledby="%s"', esc_attr($titleId))
            : sprintf(' aria-label="%s"', esc_attr($triggerLabel));

        return sprintf(
            '<div class="corex-drawer-wrap">'
            . '<button type="button" class="corex-drawer__trigger" aria-haspopup="dialog" aria-controls="%1$s">%2$s</button>'
            . '<dialog id="%1$s" class="corex-drawer corex-drawer--%7$s"%3$s>'
            . '<div class="corex-drawer__header">%4$s'
            . '<button type="button" class="corex-drawer__close" data-corex-drawer-close aria-label="%6$s">&times;</button>'
            . '</div>'
            . '<div class="corex-drawer__body">%5$s</div>'
            . '</dialog></div>',
            esc_attr($id),
            esc_html($triggerLabel),
            $name,
            $heading,
            // Inline-edited (RichText) body — safe inline HTML, scrubbed by wp_kses_post.
            wp_kses_post($body),
            esc_attr__('Close', 'corex'),
            esc_attr($side),
        );
    }
}
