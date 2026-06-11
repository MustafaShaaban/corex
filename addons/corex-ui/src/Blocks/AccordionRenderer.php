<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a set of disclosures as native `<details>`/`<summary>` elements — accessible and
 * keyboard-operable with no JavaScript. Items come from a simple text attribute: one
 * `Title | Content` per line (a line with no `|` is a title-only item). Empty input
 * renders nothing.
 */
final class AccordionRenderer implements BlockRenderer
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $items = $this->parse((string) ($attributes['items'] ?? ''));

        if ($items === []) {
            return '';
        }

        $html = '<div class="corex-accordion">';

        foreach ($items as [$title, $body]) {
            $html .= '<details class="corex-accordion__item"><summary class="corex-accordion__summary">'
                . esc_html($title) . '</summary>';

            if ($body !== '') {
                $html .= '<div class="corex-accordion__content">' . esc_html($body) . '</div>';
            }

            $html .= '</details>';
        }

        return $html . '</div>';
    }

    /**
     * @return list<array{0:string,1:string}> title/body pairs (title non-empty)
     */
    private function parse(string $raw): array
    {
        $items = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode('|', $line, 2);
            $title = trim($parts[0]);
            $body  = isset($parts[1]) ? trim($parts[1]) : '';

            if ($title !== '') {
                $items[] = [$title, $body];
            }
        }

        return $items;
    }
}
