<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * One question and its answer as a native `<details>` (spec 136, FR-030 to FR-032): accessible and
 * operable from the keyboard with no script. The question sits in the `<summary>`, as a heading of
 * the accordion's level or, with none, as plain text in a `span`; the kit's chevron follows it. A
 * `name` shared by the items of one accordion lets only one of them be open at a time, which the
 * browser does by itself.
 *
 * Drawn for an accordion item (`AccordionItemRenderer`) and for each item of an accordion saved in
 * the old shape (`AccordionRenderer`), so both print the same markup.
 */
final class AccordionItemMarkup
{
    /**
     * The kit's chevron, pointing down: its sheet turns it to point forward while the item is
     * closed (and the build mirrors that turn for right-to-left pages). Painted in the text colour.
     */
    public const CHEVRON = '<svg class="corex-accordion__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"></path></svg>';

    /** The heading levels a question may take; any other is plain text. */
    private const LEVELS = [2, 3, 4, 5, 6];

    /**
     * The heading level a question takes: 2 to 6, or 0 for plain text.
     */
    public function level(mixed $level): int
    {
        $level = is_numeric($level) ? (int) $level : 0;

        return in_array($level, self::LEVELS, true) ? $level : 0;
    }

    /**
     * @param string $question   the question, inline markup allowed (it is passed through `wp_kses_post`)
     * @param string $answerHtml the answer, already safe: what the item's blocks drew, or an old answer
     *                           its caller passed through `wp_kses_post`; nothing for no answer
     * @param int    $level      the heading level of the question, 0 for plain text
     * @param string $name       the name shared by the items of one accordion, '' for none
     */
    public function item(string $question, string $answerHtml, int $level = 0, string $name = ''): string
    {
        $tag = $this->level($level) === 0 ? 'span' : 'h' . $this->level($level);
        $html = '<details class="corex-accordion__item"' . ($name === '' ? '' : ' name="' . esc_attr($name) . '"') . '>'
            . '<summary class="corex-accordion__summary">'
            . '<' . $tag . ' class="corex-accordion__question">' . wp_kses_post($question) . '</' . $tag . '>'
            . self::CHEVRON
            . '</summary>';

        if (trim($answerHtml) !== '') {
            $html .= '<div class="corex-accordion__content">' . $answerHtml . '</div>';
        }

        return $html . '</details>';
    }
}
