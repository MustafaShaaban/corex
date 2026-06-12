<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders tabbed content with NO view JavaScript (Principle VI). Each tab is a hidden radio
 * input + a `<label>` (the clickable tab) + a panel; a CSS `:checked` sibling rule reveals the
 * active panel, so tabs work with scripts disabled and stay keyboard-operable (radios are
 * focusable; arrow keys move between tabs in the group). Tabs are a repeatable array attribute
 * (spec 029); the label + content are inline RichText (wp_kses_post). A tab with no label is
 * skipped; the first kept tab is checked; an empty set renders nothing.
 */
final class TabsRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $tabs  = is_array($attributes['tabs'] ?? null) ? $attributes['tabs'] : [];
        $group = wp_unique_id('corex-tabs-');

        $html  = '';
        $index = 0;

        foreach ($tabs as $tab) {
            $tab   = is_array($tab) ? $tab : [];
            $label = trim((string) ($tab['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $html .= $this->tab($group, $index, $label, (string) ($tab['content'] ?? ''), $index === 0);
            $index++;
        }

        if ($html === '') {
            return '';
        }

        return '<div class="corex-tabs">' . $html . '</div>';
    }

    private function tab(string $group, int $index, string $label, string $content, bool $checked): string
    {
        $id = $group . '-' . $index;

        return sprintf(
            '<input type="radio" name="%1$s" id="%2$s" class="corex-tabs__radio"%3$s />'
            . '<label for="%2$s" class="corex-tabs__label">%4$s</label>'
            . '<div class="corex-tabs__panel" role="tabpanel">%5$s</div>',
            esc_attr($group),
            esc_attr($id),
            $checked ? ' checked' : '',
            wp_kses_post($label),
            wp_kses_post($content)
        );
    }
}
