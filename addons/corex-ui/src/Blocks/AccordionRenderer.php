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
        $items = $this->parse($attributes['items'] ?? []);

        if ($items === []) {
            return '';
        }

        $html = '<div class="corex-accordion">';

        // Title + content are inline-RichText (spec 029) → wp_kses_post.
        foreach ($items as [$title, $body]) {
            $html .= '<details class="corex-accordion__item"><summary class="corex-accordion__summary">'
                . wp_kses_post($title) . '</summary>';

            if ($body !== '') {
                $html .= '<div class="corex-accordion__content">' . wp_kses_post($body) . '</div>';
            }

            $html .= '</details>';
        }

        return $html . '</div>';
    }

    /**
     * Normalize the items attribute to title/body pairs. Accepts the spec-029 array
     * (`[{title, content}]`) or, as a fallback, the legacy `Title | Content` delimited
     * string so accordions already placed keep rendering (FR-008).
     *
     * @param array<int,array{title?:string,content?:string}>|string $raw
     *
     * @return list<array{0:string,1:string}> title/body pairs (title non-empty)
     */
    private function parse(array|string $raw): array
    {
        if (is_array($raw)) {
            $items = [];
            foreach ($raw as $item) {
                $title = trim((string) ($item['title'] ?? ''));
                $body  = trim((string) ($item['content'] ?? ''));
                if ($title !== '') {
                    $items[] = [$title, $body];
                }
            }

            return $items;
        }

        return $this->parseLegacy($raw);
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function parseLegacy(string $raw): array
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
