<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Block;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders open jobs as accessible, escaped, token-styled cards (linked title +
 * department/location/type). Bounded; an empty result yields an accessible empty
 * state. Jobs come from the injected provider (testable).
 */
final class JobsRenderer implements BlockRenderer
{
    private const MAX = 50;

    public function __construct(private readonly JobProvider $jobs)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $count = min(max((int) ($attributes['count'] ?? 10), 1), self::MAX);
        $jobs  = $this->jobs->openJobs($count);

        if ($jobs === []) {
            return sprintf('<p class="corex-jobs__empty">%s</p>', esc_html__('No open positions right now.', 'corex'));
        }

        $cards = '';
        foreach ($jobs as $job) {
            $meta = implode(' · ', array_filter([$job['department'], $job['location'], $job['type']]));

            $cards .= sprintf(
                '<article class="corex-jobs__item"><h3 class="corex-jobs__title"><a href="%1$s">%2$s</a></h3>'
                . '<p class="corex-jobs__meta">%3$s</p></article>',
                esc_url($job['url']),
                esc_html($job['title']),
                esc_html($meta)
            );
        }

        return sprintf('<div class="corex-jobs">%s</div>', $cards);
    }
}
