<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders the corex/carousel block (spec 068, US9 / FR-154, FR-162) — the one slider primitive the
 * design asks for: a CSS scroll-snap row that is swipeable and keyboard-scrollable with NO JavaScript,
 * progressively enhanced by its viewScript with prev/next/dot buttons and opt-in autoplay. Autoplay is
 * off by default and, when enabled, pauses on hover/focus/blur and is disabled under reduced motion.
 * Configurable slides-per-view (1–6) powers the 1-up testimonial, 2-up project, 4-up product, and 6-up
 * logo layouts from one engine. Token-only, logical properties (RTL-correct), escaped, i18n. Every slide
 * is present in the DOM as a scrollable row, so nothing is lost before hydration or if JS fails. An empty
 * slide set renders nothing.
 */
final class CarouselRenderer implements BlockRenderer
{
    private const MIN_PER_VIEW = 1;
    private const MAX_PER_VIEW = 6;

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $slides = $this->slides($attributes);

        if ($slides === []) {
            return '';
        }

        $perView  = $this->perView($attributes);
        $autoplay = ($attributes['autoplay'] ?? false) === true;
        $label    = trim((string) ($attributes['label'] ?? ''));
        if ($label === '') {
            $label = __('Carousel', 'corex');
        }

        $total = count($slides);
        $id    = uniqid('corex-carousel-');

        return sprintf(
            '<section class="corex-carousel corex-carousel--per-%1$d" role="region"'
            . ' aria-roledescription="%2$s" aria-label="%3$s" data-corex-carousel%4$s>'
            . '<div id="%5$s" class="corex-carousel__viewport" tabindex="0" role="group" aria-label="%3$s">'
            . '<ul class="corex-carousel__track" role="list">%6$s</ul>'
            . '</div>'
            . '%7$s'
            . '</section>',
            $perView,
            esc_attr__('carousel', 'corex'),
            esc_attr($label),
            $autoplay ? ' data-corex-carousel-autoplay' : '',
            esc_attr($id),
            $this->slidesHtml($slides, $total),
            $this->controls($total),
        );
    }

    /**
     * @param list<string> $slides
     */
    private function slidesHtml(array $slides, int $total): string
    {
        $html = '';

        foreach ($slides as $index => $slide) {
            $html .= sprintf(
                '<li class="corex-carousel__slide" role="group" aria-roledescription="%1$s"'
                . ' aria-label="%2$s" data-corex-carousel-slide="%3$d">%4$s</li>',
                esc_attr__('slide', 'corex'),
                /* translators: 1: current slide number, 2: total slides. */
                esc_attr(sprintf(__('%1$d of %2$d', 'corex'), $index + 1, $total)),
                $index,
                // Inline-edited (RichText) slide body — safe inline HTML, scrubbed by wp_kses_post.
                wp_kses_post($slide),
            );
        }

        return $html;
    }

    private function controls(int $total): string
    {
        $dots = '';
        for ($i = 0; $i < $total; $i++) {
            $dots .= sprintf(
                '<button type="button" class="corex-carousel__dot" data-corex-carousel-goto="%1$d"'
                . ' aria-label="%2$s"%3$s></button>',
                $i,
                /* translators: %d: slide number. */
                esc_attr(sprintf(__('Go to slide %d', 'corex'), $i + 1)),
                $i === 0 ? ' aria-current="true"' : '',
            );
        }

        return sprintf(
            '<div class="corex-carousel__controls">'
            . '<button type="button" class="corex-carousel__arrow corex-carousel__arrow--prev"'
            . ' data-corex-carousel-prev aria-label="%1$s">&lsaquo;</button>'
            . '<div class="corex-carousel__dots" role="group" aria-label="%3$s">%4$s</div>'
            . '<button type="button" class="corex-carousel__arrow corex-carousel__arrow--next"'
            . ' data-corex-carousel-next aria-label="%2$s">&rsaquo;</button>'
            . '</div>',
            esc_attr__('Previous slide', 'corex'),
            esc_attr__('Next slide', 'corex'),
            esc_attr__('Choose slide', 'corex'),
            $dots,
        );
    }

    /**
     * @param array<string,mixed> $attributes
     * @return list<string>
     */
    private function slides(array $attributes): array
    {
        $raw    = is_array($attributes['slides'] ?? null) ? $attributes['slides'] : [];
        $slides = [];

        foreach ($raw as $slide) {
            $slide = is_array($slide) ? $slide : [];
            $body  = trim((string) ($slide['content'] ?? ''));

            if ($body !== '') {
                $slides[] = $body;
            }
        }

        return $slides;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    private function perView(array $attributes): int
    {
        $perView = (int) ($attributes['perView'] ?? self::MIN_PER_VIEW);

        return max(self::MIN_PER_VIEW, min(self::MAX_PER_VIEW, $perView));
    }
}
