<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Patterns;

defined('ABSPATH') || exit;

/**
 * The Corex section patterns — compositions of core blocks, styled only with
 * theme.json presets (color slugs + `var:preset` spacing), accessible, and
 * translation-ready. Pure: it returns the catalog; registration is the registrar's
 * job. The contact pattern composes the spec-007 `corex/form` block.
 */
final class PatternLibrary
{
    public const CATEGORY = 'corex';

    /**
     * @return list<array{name:string,title:string,content:string}>
     */
    public function patterns(): array
    {
        return [
            ['name' => 'corex/hero', 'title' => __('Hero', 'corex'), 'content' => $this->hero()],
            ['name' => 'corex/features', 'title' => __('Features', 'corex'), 'content' => $this->features()],
            ['name' => 'corex/cta', 'title' => __('Call to action', 'corex'), 'content' => $this->cta()],
            ['name' => 'corex/testimonial', 'title' => __('Testimonial', 'corex'), 'content' => $this->testimonial()],
            ['name' => 'corex/contact', 'title' => __('Contact', 'corex'), 'content' => $this->contact()],
        ];
    }

    private function hero(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"primary","textColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull has-surface-color has-primary-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center","level":1} --><h1 class="wp-block-heading has-text-align-center">%1$s</h1><!-- /wp:heading -->'
            . '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">%2$s</p><!-- /wp:paragraph -->'
            . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">'
            . '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">%3$s</a></div><!-- /wp:button -->'
            . '</div><!-- /wp:buttons --></section><!-- /wp:group -->',
            esc_html__('Build something great', 'corex'),
            esc_html__('A clear, confident sentence about what your company does and who it helps.', 'corex'),
            esc_html__('Get started', 'corex')
        );
    }

    private function features(): string
    {
        $item = fn (string $title, string $text): string => sprintf(
            '<!-- wp:column --><div class="wp-block-column">'
            . '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">%1$s</h3><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>%2$s</p><!-- /wp:paragraph --></div><!-- /wp:column -->',
            $title,
            $text
        );

        return '<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . esc_html__('What we do', 'corex') . '</h2><!-- /wp:heading -->'
            . '<!-- wp:columns --><div class="wp-block-columns">'
            . $item(esc_html__('Service one', 'corex'), esc_html__('A short description of this service and its value.', 'corex'))
            . $item(esc_html__('Service two', 'corex'), esc_html__('A short description of this service and its value.', 'corex'))
            . $item(esc_html__('Service three', 'corex'), esc_html__('A short description of this service and its value.', 'corex'))
            . '</div><!-- /wp:columns --></section><!-- /wp:group -->';
    }

    private function cta(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"accent","textColor":"ink","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull has-ink-color has-accent-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">%1$s</h2><!-- /wp:heading -->'
            . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">'
            . '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">%2$s</a></div><!-- /wp:button -->'
            . '</div><!-- /wp:buttons --></section><!-- /wp:group -->',
            esc_html__('Ready to talk?', 'corex'),
            esc_html__('Contact us', 'corex')
        );
    }

    private function testimonial(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:quote {"textAlign":"center"} --><blockquote class="wp-block-quote has-text-align-center">'
            . '<!-- wp:paragraph --><p>%1$s</p><!-- /wp:paragraph --><cite>%2$s</cite></blockquote><!-- /wp:quote -->'
            . '</section><!-- /wp:group -->',
            esc_html__('Working with this team changed how we operate.', 'corex'),
            esc_html__('A happy client', 'corex')
        );
    }

    private function contact(): string
    {
        return '<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . esc_html__('Get in touch', 'corex') . '</h2><!-- /wp:heading -->'
            . '<!-- wp:corex/form {"formSlug":"contact"} /-->'
            . '</section><!-- /wp:group -->';
    }
}
