<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders a project's structured meta (client, role, year, external link) as an accessible, escaped
 * definition list — showing only the fields that actually have a value, and rendering NOTHING when a
 * project has no meta at all. The values come from the injected provider, so this is unit-testable
 * without WordPress. It never fabricates a field: an unset value is simply omitted (honest empty).
 */
final class ProjectMetaRenderer implements BlockRenderer
{
    public function __construct(private readonly ProjectMetaProvider $meta)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $postId = (int) ($block->context['postId'] ?? 0);
        if ($postId === 0 && function_exists('get_the_ID')) {
            $postId = (int) get_the_ID();
        }

        if ($postId === 0) {
            return '';
        }

        return $this->markup($this->meta->metaFor($postId));
    }

    /**
     * Build the definition list from real values only. Separated from the WordPress post-id lookup so
     * the presentation is testable directly.
     *
     * @param array{client:string,role:string,year:string,url:string} $meta
     */
    public function markup(array $meta): string
    {
        $rows = '';
        foreach ($this->fields() as $key => $label) {
            $value = trim((string) ($meta[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $rows .= sprintf(
                '<div class="corex-project-meta__row"><dt>%s</dt><dd>%s</dd></div>',
                esc_html($label),
                esc_html($value),
            );
        }

        $url  = trim((string) ($meta['url'] ?? ''));
        $link = $url !== ''
            ? sprintf(
                '<a class="corex-project-meta__link" href="%s" rel="noopener">%s</a>',
                esc_url($url),
                esc_html__('Visit project', 'corex'),
            )
            : '';

        if ($rows === '' && $link === '') {
            return '';
        }

        $list = $rows !== '' ? '<dl class="corex-project-meta__list">' . $rows . '</dl>' : '';

        return '<div class="corex-project-meta">' . $list . $link . '</div>';
    }

    /**
     * @return array<string,string>
     */
    private function fields(): array
    {
        return [
            'client' => __('Client', 'corex'),
            'role'   => __('Role', 'corex'),
            'year'   => __('Year', 'corex'),
        ];
    }
}
