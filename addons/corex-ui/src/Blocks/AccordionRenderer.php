<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;
use Countable;

/**
 * Renders `corex/accordion` (spec 136, FR-030 to FR-032, FR-039): questions that open to show their
 * answers, as native `<details>`/`<summary>`, accessible and operable from the keyboard with no script.
 *
 * An accordion holds `corex/accordion-item` blocks, which draw themselves; this prints the root around
 * what they drew. One saved in the old shape (no inner blocks, its questions and answers in `items`) is
 * drawn from that attribute, in the same markup as the items, until an editor opens it and the editor
 * migrates it. `items` is the spec-029 array (`[{title, content}]`) or, before it, one `Title | Content`
 * per line. Nothing to show renders nothing.
 */
final class AccordionRenderer implements BlockRenderer
{
    public function __construct(private readonly AccordionItemMarkup $markup)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        if ($this->holdsBlocks($block)) {
            return trim($content) === '' ? '' : '<div class="corex-accordion">' . $content . '</div>';
        }

        $items = $this->parse($attributes['items'] ?? []);

        if ($items === []) {
            return '';
        }

        $level = $this->markup->level($attributes['headingLevel'] ?? 0);
        $name = empty($attributes['oneOpen']) ? '' : wp_unique_id('corex-accordion-');
        $html = '<div class="corex-accordion">';

        // Title and content were inline RichText (spec 029): the answer is passed through wp_kses_post.
        foreach ($items as [$title, $body]) {
            $html .= $this->markup->item($title, $body === '' ? '' : wp_kses_post($body), $level, $name);
        }

        return $html . '</div>';
    }

    private function holdsBlocks(object $block): bool
    {
        $inner = $block->inner_blocks ?? [];

        return (is_array($inner) || $inner instanceof Countable) && count($inner) > 0;
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
