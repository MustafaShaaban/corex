<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Patterns;

defined('ABSPATH') || exit;

use Corex\Blocks\SampleBlocks;

/**
 * The Corex section patterns — compositions of core blocks, styled only with
 * theme.json presets (color slugs + `var:preset` spacing), accessible, and
 * translation-ready. Pure: it returns the catalog; registration is the registrar's
 * job. The contact pattern composes the spec-007 `corex/form` block.
 *
 * They make no claim for the site (spec 115): the figures and the quote are marked as samples, and the
 * hero and call-to-action buttons go to a real address or are not drawn. Their colours are chosen for
 * contrast in the light and the dark palette: text on the section at least 4.5:1, a button against its
 * section at least 3:1.
 */
final class PatternLibrary
{
    public const CATEGORY = 'corex';

    /**
     * The class that marks a section as sample content: hidden from visitors, labelled for editors,
     * until someone changes it in the editor (corex-blocks' `SampleBlocks`, spec 115).
     */
    public const SAMPLE = SampleBlocks::MARKER;

    /**
     * The class of a button that takes its address when it is drawn: the brand's primary action link or
     * the contact page ({@see ActionLinks}, spec 115). With neither, it is not drawn.
     */
    public const ACTION_LINK = 'corex-action-link';

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
            ['name' => 'corex/section-header', 'title' => __('Section header', 'corex'), 'content' => $this->sectionHeader()],
            ['name' => 'corex/content-split', 'title' => __('Content split', 'corex'), 'content' => $this->contentSplit()],
            ['name' => 'corex/stats', 'title' => __('Stats', 'corex'), 'content' => $this->stats()],
            ['name' => 'corex/faq', 'title' => __('FAQ', 'corex'), 'content' => $this->faq()],
            ['name' => 'corex/news', 'title' => __('Latest news', 'corex'), 'content' => $this->news()],
        ];
    }

    private function hero(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"primary","textColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull has-surface-color has-primary-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center","level":1,"textColor":"surface"} --><h1 class="wp-block-heading has-text-align-center has-surface-color has-text-color">%1$s</h1><!-- /wp:heading -->'
            . '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">%2$s</p><!-- /wp:paragraph -->'
            . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">'
            . self::actionButton('surface', 'primary', '%3$s')
            . '</div><!-- /wp:buttons --></section><!-- /wp:group -->',
            esc_html__('Build something great', 'corex'),
            esc_html__('A clear, confident sentence about what your company does and who it helps.', 'corex'),
            esc_html__('Get started', 'corex')
        );
    }

    /**
     * A button with no address of its own: {@see ActionLinks} gives it one when it is drawn. Its colours
     * are its own, so it stands apart from the section behind it.
     */
    private static function actionButton(string $background, string $text, string $label): string
    {
        return sprintf(
            '<!-- wp:button {"backgroundColor":"%1$s","textColor":"%2$s","className":"%4$s"} -->'
            . '<div class="wp-block-button %4$s"><a class="wp-block-button__link has-%2$s-color has-%1$s-background-color has-text-color has-background wp-element-button">%3$s</a></div><!-- /wp:button -->',
            $background,
            $text,
            $label,
            self::ACTION_LINK,
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
            '<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"accent","textColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull has-surface-color has-accent-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center","textColor":"surface"} --><h2 class="wp-block-heading has-text-align-center has-surface-color has-text-color">%1$s</h2><!-- /wp:heading -->'
            . '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">'
            . self::actionButton('surface', 'accent-dark', '%2$s')
            . '</div><!-- /wp:buttons --></section><!-- /wp:group -->',
            esc_html__('Ready to talk?', 'corex'),
            esc_html__('Contact us', 'corex')
        );
    }

    private function testimonial(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"section","align":"full","className":"' . self::SAMPLE . '","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull ' . self::SAMPLE . '" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
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

    private function sectionHeader(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"header","align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->'
            . '<header class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--40)">'
            . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">%1$s</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph {"align":"center","textColor":"ink-soft"} --><p class="has-text-align-center has-ink-soft-color has-text-color">%2$s</p><!-- /wp:paragraph -->'
            . '</header><!-- /wp:group -->',
            esc_html__('A clear section title', 'corex'),
            esc_html__('A short supporting sentence that sets up the section below.', 'corex')
        );
    }

    private function contentSplit(): string
    {
        return sprintf(
            '<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:media-text {"mediaType":"image"} --><div class="wp-block-media-text is-stacked-on-mobile">'
            . '<figure class="wp-block-media-text__media"></figure>'
            . '<div class="wp-block-media-text__content">'
            . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">%1$s</h2><!-- /wp:heading -->'
            . '<!-- wp:paragraph --><p>%2$s</p><!-- /wp:paragraph -->'
            . '</div></div><!-- /wp:media-text --></section><!-- /wp:group -->',
            esc_html__('Tell your story beside an image', 'corex'),
            esc_html__('Pair a short narrative with a supporting image — media on one side, content on the other.', 'corex')
        );
    }

    private function stats(): string
    {
        $stat = static fn (string $value, string $label): string => sprintf(
            '<!-- wp:column --><div class="wp-block-column">'
            . '<!-- wp:corex/stat {"value":"%1$s","label":"%2$s"} /-->'
            . '</div><!-- /wp:column -->',
            $value,
            $label
        );

        return '<!-- wp:group {"tagName":"section","align":"full","className":"' . self::SAMPLE . '","backgroundColor":"surface-alt","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull ' . self::SAMPLE . ' has-surface-alt-background-color has-background" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:columns --><div class="wp-block-columns">'
            . $stat(esc_html__('100+', 'corex'), esc_html__('Projects delivered', 'corex'))
            . $stat(esc_html__('15', 'corex'), esc_html__('Years of experience', 'corex'))
            . $stat(esc_html__('98%', 'corex'), esc_html__('Client satisfaction', 'corex'))
            . '</div><!-- /wp:columns --></section><!-- /wp:group -->';
    }

    private function faq(): string
    {
        return '<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . esc_html__('Frequently asked questions', 'corex') . '</h2><!-- /wp:heading -->'
            . '<!-- wp:corex/accordion /-->'
            . '</section><!-- /wp:group -->';
    }

    private function news(): string
    {
        return '<!-- wp:group {"tagName":"section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->'
            . '<section class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">'
            . '<!-- wp:heading {"textAlign":"center"} --><h2 class="wp-block-heading has-text-align-center">' . esc_html__('Latest news', 'corex') . '</h2><!-- /wp:heading -->'
            . '<!-- wp:corex/posts {"count":3} /-->'
            . '</section><!-- /wp:group -->';
    }
}
