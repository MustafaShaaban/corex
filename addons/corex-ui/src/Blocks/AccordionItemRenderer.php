<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a `corex/accordion-item` (spec 136, FR-030 to FR-032): its question, and its answer, which
 * is whatever its inner blocks drew. The heading level and the shared name come from its accordion,
 * as block context (`corex/accordionHeadingLevel`, given by the accordion's attribute, and
 * `corex/accordionName`, given by {@see AccordionGroups}). An item with no question prints nothing.
 */
final class AccordionItemRenderer implements BlockRenderer
{
    public function __construct(private readonly AccordionItemMarkup $markup)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $question = trim((string) ($attributes['question'] ?? ''));

        if ($question === '') {
            return '';
        }

        $context = isset($block->context) && is_array($block->context) ? $block->context : [];

        return $this->markup->item(
            $question,
            $content,
            $this->markup->level($context['corex/accordionHeadingLevel'] ?? 0),
            (string) ($context[AccordionGroups::CONTEXT] ?? ''),
        );
    }
}
